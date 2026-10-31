use crate::domain::dictionary::Entry;
use serde_json::Value;
use std::{
    sync::Arc,
    time::{Duration, Instant},
};
use tokio::sync::{Mutex, Semaphore};
mod providers;
#[derive(Clone)]
pub struct Dictionary {
    client: reqwest::Client,
    slots: Arc<Semaphore>,
    cooldowns: Arc<Mutex<[Option<Instant>; 3]>>,
}
#[derive(Debug)]
pub enum LookupError {
    NotFound,
    Unavailable,
    Busy,
}
impl Default for Dictionary {
    fn default() -> Self {
        Self::new()
    }
}
impl Dictionary {
    pub fn new() -> Self {
        Self {
            client: reqwest::Client::builder()
                .timeout(Duration::from_secs(3))
                .redirect(reqwest::redirect::Policy::none())
                .build()
                .expect("dictionary HTTP client"),
            slots: Arc::new(Semaphore::new(8)),
            cooldowns: Arc::new(Mutex::new([None; 3])),
        }
    }
    async fn fetch(&self, index: usize, word: &str) -> Result<Vec<Entry>, (LookupError, Duration)> {
        let base = [
            "https://api.dictionaryapi.dev/api/v2/entries/en/",
            "https://freedictionaryapi.com/api/v1/entries/en/",
            "https://api.suvankar.cc/dictionaryapi/v1/definitions/en/",
        ][index];
        let unavailable = || (LookupError::Unavailable, Duration::from_secs(900));
        let mut url = reqwest::Url::parse(base).unwrap();
        url.path_segments_mut().unwrap().push(word);
        if index == 2 {
            url.set_query(Some("compact=true"));
        }
        let mut response = self
            .client
            .get(url)
            .send()
            .await
            .map_err(|_| unavailable())?;
        if response.status() == 404 {
            return Err((LookupError::NotFound, Duration::ZERO));
        }
        if response.status() == 429 {
            return Err((LookupError::Unavailable, Duration::from_secs(3600)));
        }
        if !response.status().is_success() {
            return Err(unavailable());
        }
        let mut body = Vec::new();
        while let Some(chunk) = response.chunk().await.map_err(|_| unavailable())? {
            if body.len() + chunk.len() > 2 * 1024 * 1024 {
                return Err(unavailable());
            }
            body.extend_from_slice(&chunk);
        }
        let payload: Value = serde_json::from_slice(&body).map_err(|_| unavailable())?;
        let entries = providers::normalize(index, word, payload).ok_or_else(unavailable)?;
        if entries.is_empty() {
            return Err((LookupError::NotFound, Duration::ZERO));
        }
        Ok(entries)
    }
    pub async fn lookup(&self, word: &str) -> Result<Vec<Entry>, LookupError> {
        let _permit = self.slots.try_acquire().map_err(|_| LookupError::Busy)?;
        self.lookup_with(|index| self.fetch(index, word)).await
    }
    async fn lookup_with<F, Fut>(&self, mut fetch: F) -> Result<Vec<Entry>, LookupError>
    where
        F: FnMut(usize) -> Fut,
        Fut: std::future::Future<Output = Result<Vec<Entry>, (LookupError, Duration)>>,
    {
        let mut unavailable = false;
        for index in 0..3 {
            if self.cooldowns.lock().await[index].is_some_and(|until| until > Instant::now()) {
                unavailable = true;
                continue;
            }
            match fetch(index).await {
                Ok(entries) => return Ok(entries),
                Err((LookupError::NotFound, _)) => {}
                Err((_, cooldown)) => {
                    unavailable = true;
                    self.cooldowns.lock().await[index] = Some(Instant::now() + cooldown);
                }
            }
        }
        Err(if unavailable {
            LookupError::Unavailable
        } else {
            LookupError::NotFound
        })
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[tokio::test]
    async fn falls_back_and_skips_unavailable_sources_on_next_lookup() {
        let dictionary = Dictionary::new();
        let mut calls = Vec::new();
        dictionary
            .lookup_with(|index| {
                calls.push(index);
                std::future::ready(if index == 2 {
                    Ok(vec![])
                } else if index == 0 {
                    Err((LookupError::Unavailable, Duration::from_secs(900)))
                } else {
                    Err((LookupError::NotFound, Duration::ZERO))
                })
            })
            .await
            .unwrap();
        assert_eq!(calls, [0, 1, 2]);
        calls.clear();
        dictionary
            .lookup_with(|index| {
                calls.push(index);
                std::future::ready(Ok(vec![]))
            })
            .await
            .unwrap();
        assert_eq!(calls, [1]);
    }
    #[tokio::test]
    async fn distinguishes_no_match_from_provider_outage() {
        let dictionary = Dictionary::new();
        assert!(matches!(
            dictionary
                .lookup_with(|_| std::future::ready(Err((LookupError::NotFound, Duration::ZERO))))
                .await,
            Err(LookupError::NotFound)
        ));
        assert!(matches!(
            dictionary
                .lookup_with(|_| std::future::ready(Err((
                    LookupError::Unavailable,
                    Duration::from_secs(3600)
                ))))
                .await,
            Err(LookupError::Unavailable)
        ));
    }
}
