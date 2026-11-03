<script setup lang="ts">
import { ref } from "vue";
import { session } from "../app/services";
import { authError } from "../app/router";
const error = ref(authError.message);
async function login() {
  error.value = "";
  try {
    await session.login();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Không thể đăng nhập.";
  }
}
</script>
<template>
  <div class="login">
    <div class="panel">
      <img src="/images/todo-icon.png" width="64" height="64" alt="Todo" />
      <h1>Todo</h1>
      <p>Sắp xếp công việc. Tập trung vào điều quan trọng.</p>
      <button class="primary" :disabled="session.state.busy" @click="login">
        Đăng nhập với Identity
      </button>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
    </div>
  </div>
</template>
