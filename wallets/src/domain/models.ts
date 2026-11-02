export interface UserProfile {
  id: string
  email: string
  name: string
  avatar: string | null
}
export interface Tokens {
  access_token: string
  expires_in: number
  token_type: string
  scope: string
}
