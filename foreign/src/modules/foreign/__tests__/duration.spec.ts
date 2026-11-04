import { expect, it } from 'vitest'
import { durationLabel } from '../presentation/duration'

it.each([
  ['PT12M34S', '12:34'],
  ['PT45S', '0:45'],
  ['PT1H2M3S', '1:02:03'],
  ['PT2H', '2:00:00'],
  ['PT0S', '0:00'],
  ['P1DT2H3M4S', '26:03:04'],
  [null, null],
  [undefined, null],
  ['', null],
  ['PT', null],
  ['P', null],
  ['invalid', null],
])('formats video duration %s as %s', (input, expected) => {
  expect(durationLabel(input)).toBe(expected)
})
