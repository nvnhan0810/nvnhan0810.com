export class LookupFailure extends Error {
  constructor(readonly reason: 'not_found' | 'unavailable' | 'unauthorized') {
    super(reason)
  }
}
