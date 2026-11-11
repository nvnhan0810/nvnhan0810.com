"""Exercise deploy.sh against disposable local Git remotes and fake build tools."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[1] / 'deploy.sh'
APPS = ['portfolio', 'identity', 'foreign', 'wallets', 'todos', 'tools']


class DeployTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='deploy-test-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.origin = self.root / 'remote.git'
        self.author = self.root / 'author'
        self.checkout = self.root / 'deployment'
        self.tools = self.root / 'tools'
        self.tools.mkdir()
        self.log = self.root / 'calls'
        self.env = dict(os.environ, GIT_CONFIG_NOSYSTEM='1', GIT_CONFIG_GLOBAL='/dev/null',
                        PATH=f'{self.tools}:{os.environ["PATH"]}', DEPLOY_TEST_LOG=str(self.log))
        self.git('init', '--bare', '--initial-branch=main', str(self.origin))
        self.git('init', '--initial-branch=main', str(self.author))
        self.git('config', 'user.name', 'Deploy Test', cwd=self.author)
        self.git('config', 'user.email', 'deploy@example.test', cwd=self.author)
        # Keep absolute production Supervisor paths inside this disposable fixture.
        (self.author / 'deploy.sh').write_text(
            SCRIPT.read_text().replace('/usr/bin/supervisorctl', str(self.tools / 'supervisorctl')))
        (self.author / 'backend').mkdir()
        (self.author / 'backend/Cargo.toml').write_text('# fixture\n')
        for app in APPS:
            (self.author / app).mkdir()
            (self.author / app / 'package.json').write_text('{"scripts":{"build":"fixture"}}\n')
        self.git('add', '.', cwd=self.author)
        self.git('commit', '-m', 'Initial applications', cwd=self.author)
        self.git('remote', 'add', 'origin', str(self.origin), cwd=self.author)
        self.git('push', '-u', 'origin', 'main', cwd=self.author)
        self.git('clone', str(self.origin), str(self.checkout))
        for command in ['cargo', 'npm', 'supervisorctl']:
            tool = self.tools / command
            tool.write_text('''#!/usr/bin/env bash
printf '%s|%s|%s\\n' "${PWD##*/}" "${0##*/}" "$*" >> "$DEPLOY_TEST_LOG"
if [[ -n "${DEPLOY_TEST_FAIL:-}" && "$*" == *"$DEPLOY_TEST_FAIL"* ]]; then exit 7; fi
if [[ "${0##*/}" == supervisorctl ]]; then
  if [[ "$1" == status ]]; then
    state=RUNNING
    if [[ -f "$DEPLOY_TEST_LOG.state.$2" ]]; then state="$(cat "$DEPLOY_TEST_LOG.state.$2")"; fi
    printf '%s %s\\n' "$2" "$state"
    [[ "$state" == RUNNING ]] || exit 3
  elif [[ "$1" == stop || "$1" == start ]]; then
    state=STOPPED
    [[ "$1" != start ]] || state=RUNNING
    shift
    for service in "$@"; do printf '%s' "$state" > "$DEPLOY_TEST_LOG.state.$service"; done
  fi
fi
''')
            tool.chmod(0o755)

    def git(self, *args, cwd=None):
        return subprocess.run(['git', *args], cwd=cwd or self.root, env=self.env,
                              capture_output=True, text=True, check=True).stdout.strip()

    def change(self, *paths):
        for name in paths:
            path = self.author / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('new content\n')
        self.git('add', '.', cwd=self.author)
        self.git('commit', '-m', 'Remote update', cwd=self.author)
        self.git('push', cwd=self.author)

    def deploy(self, *args, fail=None, check=True):
        env = dict(self.env)
        if fail:
            env['DEPLOY_TEST_FAIL'] = fail
        result = subprocess.run(['bash', str(self.checkout / 'deploy.sh'), *args],
                                cwd=self.root, env=env, capture_output=True, text=True)
        if check:
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        return result

    def calls(self):
        return self.log.read_text().splitlines() if self.log.exists() else []

    def test_no_changes_do_not_build_or_restart(self):
        self.deploy()
        self.assertEqual(self.calls(), [])

    def test_backend_builds_release_and_restarts_both_services(self):
        self.change('backend/src/new.rs')
        self.deploy()
        self.assertEqual(self.calls(), [
            'backend|cargo|build --release --locked --workspace',
            'deployment|supervisorctl|status nvnhan0810-backend-queue',
            'deployment|supervisorctl|stop nvnhan0810-backend-queue',
            'deployment|supervisorctl|status nvnhan0810-backend',
            'deployment|supervisorctl|stop nvnhan0810-backend',
            'backend|cargo|run --release --locked -p console --bin migrate -- up',
            'backend|cargo|run --release --locked -p console --bin console -- master-data',
            'deployment|supervisorctl|start nvnhan0810-backend nvnhan0810-backend-queue',
        ])
        self.assertEqual(self.git('rev-parse', 'HEAD', cwd=self.checkout),
                         self.git('rev-parse', 'HEAD', cwd=self.author))
        self.deploy()
        self.assertEqual(len(self.calls()), 8)

    def test_frontend_selection_and_only_portfolio_restarts(self):
        self.change('foreign/src/new.ts', 'portfolio/src/new.ts')
        self.deploy()
        self.assertEqual(self.calls(), [
            'portfolio|npm|i --include=dev --no-save', 'portfolio|npm|run build',
            'foreign|npm|i --include=dev --no-save', 'foreign|npm|run build',
            'deployment|supervisorctl|restart nvnhan0810-com',
        ])

    def test_static_frontend_does_not_restart_services(self):
        self.change('todos/src/new.ts', 'tools/src/new.ts')
        self.deploy()
        self.assertEqual(self.calls(), [
            'todos|npm|i --include=dev --no-save', 'todos|npm|run build',
            'tools|npm|i --include=dev --no-save', 'tools|npm|run build',
        ])

    def test_dry_run_does_not_pull_build_restart_or_record_success(self):
        before = self.git('rev-parse', 'HEAD', cwd=self.checkout)
        self.change('backend/src/new.rs', 'portfolio/src/new.ts')
        result = self.deploy('--dry-run')
        self.assertEqual(before, self.git('rev-parse', 'HEAD', cwd=self.checkout))
        self.assertEqual(self.calls(), [])
        self.assertIn('nvnhan0810-backend-queue', result.stdout)
        self.assertEqual(list((self.checkout / '.git/deploy-state').glob('*.last-success')), [])

    def test_dirty_checkout_stops_before_pull(self):
        before = self.git('rev-parse', 'HEAD', cwd=self.checkout)
        (self.checkout / 'local.txt').write_text('local change')
        self.change('foreign/src/new.ts')
        result = self.deploy(check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('local changes', result.stderr)
        self.assertEqual(before, self.git('rev-parse', 'HEAD', cwd=self.checkout))
        self.assertEqual(self.calls(), [])

    def test_diverged_or_ahead_checkout_is_not_merged(self):
        self.git('config', 'user.name', 'Local', cwd=self.checkout)
        self.git('config', 'user.email', 'local@example.test', cwd=self.checkout)
        (self.checkout / 'local.txt').write_text('local commit')
        self.git('add', '.', cwd=self.checkout)
        self.git('commit', '-m', 'Local only', cwd=self.checkout)
        self.change('foreign/src/new.ts')
        result = self.deploy(check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('diverges', result.stderr)
        self.assertEqual(self.calls(), [])

    def test_failed_build_is_retried_even_after_pull(self):
        self.change('backend/src/new.rs', 'portfolio/src/new.ts')
        result = self.deploy(fail='run build', check=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(any('|supervisorctl|' in call for call in self.calls()))
        self.log.unlink()
        self.deploy()
        self.assertIn('backend|cargo|build --release --locked --workspace', self.calls())
        self.assertIn('portfolio|npm|run build', self.calls())
        self.assertEqual(self.calls()[-1],
                         'deployment|supervisorctl|restart nvnhan0810-com')

    def test_all_retry_keeps_full_build_plan_without_git_changes(self):
        self.deploy('--all', fail='run build', check=False)
        self.log.unlink()
        self.deploy()
        self.assertIn('backend|cargo|build --release --locked --workspace', self.calls())
        for app in APPS:
            self.assertIn(f'{app}|npm|run build', self.calls())
        self.assertFalse(any('index|' in call or 'ai-embedding' in call for call in self.calls()))

    def test_database_update_failure_leaves_backend_stopped_and_can_retry(self):
        for failing_step in ['--bin migrate', '-- master-data']:
            with self.subTest(step=failing_step):
                result = self.deploy('--all', fail=failing_step, check=False)
                self.assertNotEqual(result.returncode, 0)
                self.assertFalse(any('|supervisorctl|start ' in call for call in self.calls()))
                self.assertFalse(any('|supervisorctl|restart ' in call for call in self.calls()))
                if failing_step == '--bin migrate':
                    self.assertFalse(any('-- master-data' in call for call in self.calls()))
                self.log.unlink()
                self.deploy()
                self.assertIn('deployment|supervisorctl|start nvnhan0810-backend nvnhan0810-backend-queue', self.calls())
                self.log.unlink()

    def test_retired_sources_are_not_deployed(self):
        self.change('index/removed.txt', 'ai-embedding-enrichment/removed.txt')
        self.deploy()
        self.assertEqual(self.calls(), [])

    def test_supervisor_failure_is_retried(self):
        self.change('portfolio/src/new.ts')
        result = self.deploy(fail='restart', check=False)
        self.assertNotEqual(result.returncode, 0)
        self.log.unlink()
        self.deploy()
        self.assertEqual(self.calls()[-1], 'deployment|supervisorctl|restart nvnhan0810-com')


if __name__ == '__main__':
    unittest.main()
