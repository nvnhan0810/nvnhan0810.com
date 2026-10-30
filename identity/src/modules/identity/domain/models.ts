export interface IdentityClient {
  id: string
  client_id: string
  name: string
  domain: string | null
  client_type: 'public' | 'confidential'
  redirect_uris: string[]
  scopes: string[]
  enabled: boolean
  version: number
}
export interface ClientInput {
  client_id: string
  name: string
  domain: string | null
  client_type: 'public' | 'confidential'
  redirect_uris: string[]
  scopes: string[]
  enabled: boolean
}
export interface ClientUpdate extends Omit<ClientInput, 'client_id' | 'client_type'> {
  version: number
}
export interface UserProfile {
  id: string
  email: string
  name: string
  avatar: string | null
}
export interface IdentityUser extends UserProfile {
  enabled: boolean
  is_system_root: boolean
}
export interface ClientResult {
  client: IdentityClient
  client_secret?: string | null
}
export interface ClientList {
  clients: IdentityClient[]
  next_cursor: string | null
}
export interface UserList {
  users: IdentityUser[]
  next_cursor: string | null
}
export interface Tokens {
  access_token: string
  refresh_token: string
  expires_in: number
  token_type: string
  scope: string
}
export const ROOT_EMAIL = 'nguyenvannhan0810@gmail.com'
