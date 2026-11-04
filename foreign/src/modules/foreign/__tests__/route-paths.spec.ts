import { expect, it } from 'vitest'
import { fromPagePath, RoutePath, toPagePath } from '../../../app/router/paths'

it('maps page URLs to backend paths and restores redirects with query strings', () => {
  for (const path of [
    `${RoutePath.lookup}?q=hello`,
    RoutePath.vocabularyDetail.replace(':id', '12'),
    RoutePath.related.replace(':word', 'hello%20world'),
    RoutePath.transcriptEdit.replace(':id', '34'),
    RoutePath.wordSearch,
  ]) {
    expect(toPagePath(path)).toBe(`/home${path}`)
    expect(fromPagePath(`/home${path}`)).toBe(path)
  }
})

it('preserves authentication and unrelated paths', () => {
  for (const path of [
    RoutePath.login,
    RoutePath.authCallback,
    '/unrelated',
  ]) {
    expect(toPagePath(path)).toBe(path)
    expect(fromPagePath(path)).toBe(path)
  }
})
