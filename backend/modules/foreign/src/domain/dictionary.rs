use serde::{Deserialize, Serialize};

#[derive(Debug, Deserialize, Serialize)]
pub struct Entry {
    pub word: String,
    #[serde(default)]
    pub phonetic: String,
    #[serde(default)]
    pub phonetics: Vec<Phonetic>,
    pub meanings: Vec<Meaning>,
}
#[derive(Debug, Deserialize, Serialize)]
pub struct Phonetic {
    #[serde(default)]
    pub text: String,
    #[serde(default)]
    pub audio: String,
}
#[derive(Debug, Deserialize, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct Meaning {
    pub part_of_speech: String,
    pub definitions: Vec<Definition>,
    #[serde(default)]
    pub synonyms: Vec<String>,
    #[serde(default)]
    pub antonyms: Vec<String>,
}
#[derive(Debug, Deserialize, Serialize)]
pub struct Definition {
    pub definition: String,
    #[serde(default)]
    pub example: String,
    #[serde(default)]
    pub synonyms: Vec<String>,
    #[serde(default)]
    pub antonyms: Vec<String>,
}

pub fn normalize_word(word: &str) -> Option<String> {
    let word = word.trim().to_lowercase();
    if word.is_empty()
        || word.len() > 100
        || !word
            .chars()
            .all(|c| c.is_ascii_alphabetic() || matches!(c, ' ' | '\'' | '-'))
    {
        return None;
    }
    Some(word)
}
#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn accepts_words_and_phrases_but_rejects_paths_and_empty_queries() {
        assert_eq!(normalize_word(" Hello ").as_deref(), Some("hello"));
        assert!(normalize_word("mother-in-law").is_some());
        for word in ["", "  ", "../hello", "hello?x=1", "こんにちは"] {
            assert!(normalize_word(word).is_none());
        }
        assert!(normalize_word(&"a".repeat(101)).is_none());
    }
}

#[cfg(test)]
mod provider_contract_tests {
    use super::*;
    #[test]
    fn decodes_meanings_and_optional_fields_without_fabricating_data() {
        let entries: Vec<Entry> = serde_json::from_str(r#"[{"word":"hello","phonetics":[{"text":"hello"}],"meanings":[{"partOfSpeech":"noun","definitions":[{"definition":"A greeting.","example":"Hello there."}]}]}]"#).unwrap();
        let entry = &entries[0];
        assert_eq!(entry.phonetics[0].audio, "");
        assert_eq!(entry.meanings[0].definitions[0].example, "Hello there.");
        assert!(entry.meanings[0].synonyms.is_empty());
        assert_eq!(
            serde_json::to_value(entry).unwrap()["meanings"][0]["partOfSpeech"],
            "noun"
        );
        assert!(serde_json::from_str::<Vec<Entry>>(r#"{"title":"No Definitions Found"}"#).is_err());
    }
}
