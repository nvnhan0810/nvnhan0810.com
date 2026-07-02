import type { Locale } from "@/ts/i18n";
import type {
  PostAgentChatContext,
  PostAgentChatResponse,
  PostAgentEdits,
  PostAgentMessage,
  PostAgentSessionResponse,
} from "@/ts/types/postAgent";
import { JsonFetchError, jsonFetch } from "@/ts/utils/jsonFetch";
import { useCallback, useEffect, useRef, useState } from "react";

const POST_AGENT_SESSION_URL = "/admin/post-agent/session";
const POST_AGENT_CHAT_URL = "/admin/post-agent/chat";
const POST_AGENT_CANCEL_URL = "/admin/post-agent/cancel";

function buildSessionUrl(postId?: number): string {
  if (!postId) {
    return POST_AGENT_SESSION_URL;
  }

  return `${POST_AGENT_SESSION_URL}?post_id=${postId}`;
}

type UsePostAgentOptions = {
  postId?: number;
  context: PostAgentChatContext;
  configured: boolean;
  onApplyEdits: (edits: PostAgentEdits) => void;
};

export default function usePostAgent({
  postId,
  context,
  configured,
  onApplyEdits,
}: UsePostAgentOptions) {
  const [sessionId, setSessionId] = useState("");
  const [messages, setMessages] = useState<PostAgentMessage[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isBootstrapping, setIsBootstrapping] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);
  const pendingMessageRef = useRef<string | null>(null);
  const loadedPostIdRef = useRef<number | undefined>(undefined);

  useEffect(() => {
    if (!configured) {
      setIsBootstrapping(false);
      return;
    }

    if (!postId) {
      loadedPostIdRef.current = undefined;
      setSessionId("");
      setMessages([]);
      setIsBootstrapping(false);
      return;
    }

    if (loadedPostIdRef.current === postId) {
      setIsBootstrapping(false);
      return;
    }

    let cancelled = false;
    setIsBootstrapping(true);
    setError(null);

    const loadSession = async () => {
      try {
        const data = await jsonFetch<PostAgentSessionResponse>(
          buildSessionUrl(postId)
        );

        if (cancelled) {
          return;
        }

        loadedPostIdRef.current = postId;
        setSessionId(data.session_id ?? "");
        setMessages(data.messages ?? []);
      } catch (loadError) {
        if (!cancelled) {
          setError(
            loadError instanceof Error
              ? loadError.message
              : "Không tải được lịch sử chat."
          );
        }
      } finally {
        if (!cancelled) {
          setIsBootstrapping(false);
        }
      }
    };

    void loadSession();

    return () => {
      cancelled = true;
    };
  }, [configured, postId]);

  const ensureSessionId = useCallback(() => {
    if (sessionId) {
      return sessionId;
    }

    const nextSessionId = crypto.randomUUID();
    setSessionId(nextSessionId);

    return nextSessionId;
  }, [sessionId]);

  const cancelMessage = useCallback(async () => {
    if (!isLoading) {
      return null;
    }

    abortControllerRef.current?.abort();

    try {
      await jsonFetch(POST_AGENT_CANCEL_URL, {
        method: "POST",
      });
    } catch {
      // Client abort is enough for UI reset.
    }

    const pendingMessage = pendingMessageRef.current;
    pendingMessageRef.current = null;
    setMessages((current) => {
      const lastMessage = current[current.length - 1];

      if (lastMessage?.role === "user") {
        return current.slice(0, -1);
      }

      return current;
    });
    setIsLoading(false);
    setError(null);

    return pendingMessage;
  }, [isLoading]);

  const sendMessage = useCallback(
    async (message: string) => {
      const trimmed = message.trim();

      if (!trimmed || isLoading || !configured) {
        return;
      }

      const activeSessionId = ensureSessionId();
      const abortController = new AbortController();

      abortControllerRef.current = abortController;
      pendingMessageRef.current = trimmed;
      setIsLoading(true);
      setError(null);

      const optimisticUserMessage: PostAgentMessage = {
        role: "user",
        content: trimmed,
        created_at: new Date().toISOString(),
      };

      setMessages((current) => [...current, optimisticUserMessage]);

      try {
        const data = await jsonFetch<PostAgentChatResponse>(POST_AGENT_CHAT_URL, {
          method: "POST",
          signal: abortController.signal,
          body: {
            message: trimmed,
            session_id: activeSessionId,
            post_id: postId ?? null,
            context,
          },
        });

        pendingMessageRef.current = null;
        setSessionId(data.session_id);
        setMessages(data.messages);
        loadedPostIdRef.current = postId;

        const hasEdits =
          Object.keys(data.edits?.locales ?? {}).length > 0 ||
          Object.keys(data.edits?.source_urls ?? {}).length > 0;

        if (hasEdits) {
          onApplyEdits(data.edits);
        }
      } catch (sendError) {
        if (sendError instanceof JsonFetchError && sendError.cancelled) {
          setMessages((current) => {
            const lastMessage = current[current.length - 1];

            if (lastMessage?.role === "user") {
              return current.slice(0, -1);
            }

            return current;
          });

          return;
        }

        setMessages((current) => current.slice(0, -1));
        pendingMessageRef.current = null;
        setError(
          sendError instanceof Error
            ? sendError.message
            : "Gửi tin nhắn thất bại."
        );
      } finally {
        if (abortControllerRef.current === abortController) {
          abortControllerRef.current = null;
        }

        setIsLoading(false);
      }
    },
    [configured, context, ensureSessionId, isLoading, onApplyEdits, postId]
  );

  return {
    messages,
    isLoading,
    isBootstrapping,
    error,
    configured,
    sendMessage,
    cancelMessage,
  };
}

export type { Locale };
