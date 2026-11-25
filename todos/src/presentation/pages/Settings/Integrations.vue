<script setup lang="ts">
import { ref } from "vue";
import { useRoute } from "vue-router";
import { Link2, Unlink } from "lucide-vue-next";
import {
  agentStatus,
  startAgentLink,
  unlinkAgent,
  type AgentLinkStatus,
} from "../../../application/agent-link";
import { errorMessage } from "../../../infrastructure/transport";
import { t } from "../../../application/locale";
const props = defineProps<{ agent: AgentLinkStatus }>();
const route = useRoute();
const status = ref<AgentLinkStatus>({ ...props.agent });
const busy = ref(false),
  error = ref(""),
  notice = ref(
    route.query.linked === "1" && props.agent.linked ? t("agentLinked") : "",
  );
async function connect() {
  busy.value = true;
  error.value = "";
  try {
    window.location.assign(await startAgentLink());
  } catch (e) {
    error.value = errorCode(e);
    busy.value = false;
  }
}
async function disconnect() {
  if (!confirm(t("agentUnlinkConfirm"))) return;
  busy.value = true;
  error.value = "";
  notice.value = "";
  try {
    const result = await unlinkAgent();
    status.value = await agentStatus();
    notice.value = result.agent_notified
      ? t("agentUnlinked")
      : t("agentUnlinkedLocal");
  } catch (e) {
    error.value = errorCode(e);
  } finally {
    busy.value = false;
  }
}
function errorCode(e: unknown) {
  const code = e instanceof Error ? e.message : "";
  return code === "integration_disabled" ? t("agentDisabled") : errorMessage(e);
}
</script>
<template>
  <div class="section-heading">
    <div>
      <h1>{{ t("integrations") }}</h1>
      <p class="muted">{{ t("agentIntro") }}</p>
    </div>
  </div>
  <p v-if="error" role="alert" class="error">{{ error }}</p>
  <p v-if="notice" role="status" class="muted">{{ notice }}</p>
  <section class="panel" aria-labelledby="agent-link-title">
    <h2 id="agent-link-title">Agent</h2>
    <p v-if="!status.enabled" class="muted">{{ t("agentDisabled") }}</p>
    <template v-else-if="status.linked">
      <p>
        {{ t("agentConnectedAs") }} <strong>{{ status.email }}</strong>
      </p>
      <p v-if="status.last_seen_at" class="muted">
        {{ t("agentLastSeen") }}
        {{ new Date(status.last_seen_at).toLocaleString() }}
      </p>
      <button class="button" :disabled="busy" @click="disconnect">
        <Unlink :size="16" />{{ t("agentDisconnect") }}
      </button>
    </template>
    <template v-else>
      <p class="muted">{{ t("agentNotConnected") }}</p>
      <button class="button primary" :disabled="busy" @click="connect">
        <Link2 :size="16" />{{ t("agentConnect") }}
      </button>
    </template>
  </section>
</template>
