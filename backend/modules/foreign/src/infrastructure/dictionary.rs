use crate::domain::dictionary::Entry;
use std::{sync::Arc, time::Duration};
use tokio::sync::Semaphore;

#[derive(Clone)]
pub struct Dictionary {
    client: reqwest::Client,
    slots: Arc<Semaphore>,
}
#[derive(Debug)]
pub enum LookupError {
    NotFound,
    Unavailable,
    Busy,
}
impl Dictionary {
    pub fn new() -> Self {
        Self {
            client: reqwest::Client::builder()
                .timeout(Duration::from_secs(8))
                .redirect(reqwest::redirect::Policy::none())
                .build()
                .expect("dictionary HTTP client"),
            slots: Arc::new(Semaphore::new(8)),
        }
    }
    pub async fn lookup(&self, word: &str) -> Result<Vec<Entry>, LookupError> {
        let _permit = self.slots.try_acquire().map_err(|_| LookupError::Busy)?;
        let mut url =
            reqwest::Url::parse("https://api.dictionaryapi.dev/api/v2/entries/en/").unwrap();
        url.path_segments_mut().unwrap().push(word);
        let mut response = self
            .client
            .get(url)
            .send()
            .await
            .map_err(|_| LookupError::Unavailable)?;
        if response.status() == 404 {
            return Err(LookupError::NotFound);
        }
        if !response.status().is_success() {
            return Err(LookupError::Unavailable);
        }
        const MAX: usize = 2 * 1024 * 1024;
        let mut body = Vec::new();
        while let Some(chunk) = response
            .chunk()
            .await
            .map_err(|_| LookupError::Unavailable)?
        {
            if body.len() + chunk.len() > MAX {
                return Err(LookupError::Unavailable);
            }
            body.extend_from_slice(&chunk);
        }
        let entries: Vec<Entry> =
            serde_json::from_slice(&body).map_err(|_| LookupError::Unavailable)?;
        if entries.is_empty()
            || entries
                .iter()
                .all(|e| e.meanings.iter().all(|m| m.definitions.is_empty()))
        {
            return Err(LookupError::NotFound);
        }
        Ok(entries)
    }
}
