export enum RoutePath {
  index = '/',
  login = '/login',
  authCallback = '/auth/callback',
  lookup = '/lookup',
  related = '/word/:word',
  vocabulary = '/vocab',
  vocabularyDetail = '/vocab/:id',
  games = '/quiz',
  puzzle = '/puzzle',
  quiz = '/quiz/play',
  scramble = '/puzzle/scramble',
  wordle = '/puzzle/wordle',
  hangman = '/puzzle/hangman',
  wordSearch = '/puzzle/word-search',
  channels = '/youtube/channels',
  media = '/media',
  mediaDetail = '/media/:id',
  transcriptEdit = '/media/:id/transcript/edit',
  profile = '/profile',
  notFound = '/:pathMatch(.*)*',
}

// The page transport still uses the backend's /home paths.
const pagePaths = [
  RoutePath.lookup,
  RoutePath.related,
  RoutePath.vocabulary,
  RoutePath.vocabularyDetail,
  RoutePath.games,
  RoutePath.puzzle,
  RoutePath.quiz,
  RoutePath.scramble,
  RoutePath.wordle,
  RoutePath.hangman,
  RoutePath.wordSearch,
  RoutePath.media,
  RoutePath.mediaDetail,
  RoutePath.transcriptEdit,
  RoutePath.profile,
]

export function toPagePath(path: string): string {
  const pathname = path.split(/[?#]/)[0] ?? path
  return pagePaths.some((pattern) =>
    new RegExp(`^${pattern.replace(/:[^/]+/g, '[^/]+')}$`).test(pathname),
  )
    ? `/home${path}`
    : path
}

export function fromPagePath(path: string): string {
  const candidate = path.replace(/^\/home(?=\/)/, '')
  return toPagePath(candidate) === path ? candidate : path
}
