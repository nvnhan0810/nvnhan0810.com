<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ArrowRight, LoaderCircle } from 'lucide-vue-next'
import type { ClientInput, IdentityClient } from '../../domain/models'
import { clientInput } from '../../application/client-form'
import type { ClientDraft } from '../../application/client-form'
const props = defineProps<{ client: IdentityClient | null; busy: boolean; error: string }>()
const emit = defineEmits<{ save: [input: ClientInput]; cancel: [] }>()
const localError = ref('')
const draft = reactive<ClientDraft>({
  client_id: props.client?.client_id || '',
  name: props.client?.name || '',
  domain: props.client?.domain || '',
  client_type: props.client?.client_type || 'public',
  redirects: props.client?.redirect_uris.join('\n') || '',
  scopes: props.client?.scopes.join('\n') || 'profile',
  enabled: props.client?.enabled ?? true,
})
function submit() {
  localError.value = ''
  try {
    emit('save', clientInput(draft))
  } catch (error) {
    localError.value = error instanceof Error ? error.message : 'Dữ liệu không hợp lệ.'
  }
}
</script>
<template>
  <form @submit.prevent="submit">
    <div class="form-body">
      <div v-if="localError || error" class="alert error" role="alert">
        {{ localError || error }}
      </div>
      <div class="field-row">
        <label class="field"
          >Tên ứng dụng<span class="required">*</span
          ><input
            v-model="draft.name"
            autofocus
            required
            maxlength="255"
            placeholder="Ví dụ: Wallets"
            :disabled="busy" /></label
        ><label class="field"
          >Client ID<span class="required">*</span
          ><input
            v-model="draft.client_id"
            required
            maxlength="64"
            pattern="[a-z0-9][a-z0-9\-]*"
            placeholder="wallets-web"
            :disabled="busy || !!client"
          /><small>{{
            client ? 'Client ID không thay đổi sau khi tạo.' : 'Chữ thường, số và dấu gạch ngang.'
          }}</small></label
        >
      </div>
      <label class="field"
        >Domain<input
          v-model="draft.domain"
          placeholder="https://wallets.nvnhan0810.com"
          :disabled="busy"
        /><small>Địa chỉ hiển thị của ứng dụng, không phải callback.</small></label
      >
      <fieldset class="type-picker">
        <legend>Loại client</legend>
        <label :class="{ selected: draft.client_type === 'public' }"
          ><input
            v-model="draft.client_type"
            type="radio"
            value="public"
            :disabled="busy || !!client"
          /><strong>Public</strong><span>SPA, mobile · PKCE, không secret</span></label
        ><label :class="{ selected: draft.client_type === 'confidential' }"
          ><input
            v-model="draft.client_type"
            type="radio"
            value="confidential"
            :disabled="busy || !!client"
          /><strong>Confidential</strong><span>Backend giữ client secret</span></label
        >
      </fieldset>
      <label class="field"
        >Callback URIs<span class="required">*</span
        ><textarea
          v-model="draft.redirects"
          required
          rows="3"
          maxlength="10000"
          spellcheck="false"
          placeholder="https://wallets.nvnhan0810.com/auth/sso/callback"
          :disabled="busy"
        /><small
          >Mỗi URI một dòng. Identity so khớp chính xác; local dùng IP 127.0.0.1.</small
        ></label
      ><label class="field"
        >Scopes<span class="required">*</span
        ><textarea
          v-model="draft.scopes"
          required
          rows="3"
          maxlength="10000"
          spellcheck="false"
          placeholder="profile&#10;wallets:read&#10;wallets:write"
          :disabled="busy"
        /><small>Mỗi scope một dòng hoặc phân tách bằng khoảng trắng.</small></label
      ><label class="checkbox-field"
        ><input v-model="draft.enabled" type="checkbox" :disabled="busy" /><span
          ><strong>Bật client</strong
          ><small>Cho phép ứng dụng bắt đầu đăng nhập SSO và nhận token.</small></span
        ></label
      >
    </div>
    <div class="modal-actions">
      <button type="button" class="button secondary" :disabled="busy" @click="emit('cancel')">
        Hủy</button
      ><button class="button primary" :disabled="busy">
        <LoaderCircle v-if="busy" class="spin" :size="16" /><ArrowRight v-else :size="16" />{{
          busy ? 'Đang lưu…' : client ? 'Lưu thay đổi' : 'Tạo client'
        }}
      </button>
    </div>
  </form>
</template>
