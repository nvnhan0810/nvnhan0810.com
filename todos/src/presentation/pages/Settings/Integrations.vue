<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { Bot, CircleCheck, Info, Link2, Unlink } from "lucide-vue-next";
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
const state = computed(() =>
  !status.value.enabled
    ? "disabled"
    : status.value.linked
      ? "connected"
      : "not-connected",
);
const stateLabel = computed(
  () =>
    ({
      disabled: t("agentStatusDisabled"),
      connected: t("agentStatusConnected"),
      "not-connected": t("agentStatusNotConnected"),
    })[state.value],
);
function formatDate(value?: string | null) {
  return value ? new Date(value).toLocaleString() : "";
}
function errorCode(e: unknown) {
  const code = e instanceof Error ? e.message : "";
  return code === "integration_disabled" ? t("agentDisabled") : errorMessage(e);
}
</script>
<template>
  <div class="settings-page">
    <div class="section-heading">
      <div>
        <h1>{{ t("integrations") }}</h1>
        <p class="muted">{{ t("agentIntro") }}</p>
      </div>
    </div>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="notice" role="status" class="notice">
      <CircleCheck :size="16" />{{ notice }}
    </p>
    <section class="panel integration-card" aria-labelledby="agent-link-title">
      <header class="integration-head">
        <span class="integration-icon" aria-hidden="true"
          ><Bot :size="20"
        /></span>
        <div class="integration-title">
          <h2 id="agent-link-title">NVN Agent</h2>
          <p class="muted">{{ t("agentCardHint") }}</p>
        </div>
        <span class="integration-status" :class="state"
          ><i aria-hidden="true"></i>{{ stateLabel }}</span
        >
      </header>
      <div v-if="!status.enabled" class="integration-body">
        <p class="integration-hint">
          <Info :size="16" />{{ t("agentDisabled") }}
        </p>
      </div>
      <template v-else-if="status.linked">
        <dl class="integration-details">
          <div>
            <dt>{{ t("agentAccount") }}</dt>
            <dd>
              <strong>{{ status.email }}</strong>
            </dd>
          </div>
          <div v-if="status.linked_at">
            <dt>{{ t("agentLinkedAt") }}</dt>
            <dd>{{ formatDate(status.linked_at) }}</dd>
          </div>
          <div>
            <dt>{{ t("agentLastSeenLabel") }}</dt>
            <dd>
              {{ formatDate(status.last_seen_at) || t("agentNeverSeen") }}
            </dd>
          </div>
        </dl>
        <footer class="integration-actions">
          <button class="button danger" :disabled="busy" @click="disconnect">
            <Unlink :size="16" />{{ t("agentDisconnect") }}
          </button>
        </footer>
      </template>
      <template v-else>
        <div class="integration-body">
          <p class="muted">{{ t("agentNotConnected") }}</p>
        </div>
        <footer class="integration-actions">
          <button class="button primary" :disabled="busy" @click="connect">
            <Link2 :size="16" />{{ t("agentConnect") }}
          </button>
        </footer>
      </template>
    </section>
  </div>
</template>
