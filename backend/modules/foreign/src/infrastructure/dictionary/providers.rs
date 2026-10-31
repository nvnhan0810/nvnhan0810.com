use crate::domain::dictionary::{Definition, Entry, Meaning, Phonetic};
use serde_json::Value;
fn text(value: &Value) -> String {
    value.as_str().unwrap_or_default().to_owned()
}
fn strings(value: &Value) -> Vec<String> {
    value
        .as_array()
        .into_iter()
        .flatten()
        .filter_map(|v| v.as_str().map(str::to_owned))
        .collect()
}
pub(super) fn normalize(source: usize, word: &str, payload: Value) -> Option<Vec<Entry>> {
    if source == 0 {
        let mut entries: Vec<Entry> = serde_json::from_value(payload).ok()?;
        entries.retain(|e| {
            e.meanings.iter().any(|m| {
                m.definitions
                    .iter()
                    .any(|d| !d.definition.trim().is_empty())
            })
        });
        return Some(entries);
    }
    let rows = payload
        .get(if source == 1 { "entries" } else { "meanings" })?
        .as_array()?;
    let mut meanings = Vec::new();
    let mut phonetics = Vec::new();
    for row in rows {
        if source == 1
            && row["language"]["code"]
                .as_str()
                .is_some_and(|lang| lang != "en")
        {
            continue;
        }
        if let Some(pronunciations) = row["pronunciations"].as_array() {
            for p in pronunciations {
                phonetics.push(Phonetic {
                    text: text(&p["text"]),
                    audio: String::new(),
                });
            }
        }
        let mut definitions = Vec::new();
        for sense in row["senses"].as_array().into_iter().flatten() {
            let definition = if source == 2 {
                strings(&sense["glosses"])
                    .into_iter()
                    .next()
                    .unwrap_or_else(|| text(&sense["definition"]))
            } else {
                text(&sense["definition"])
            };
            if definition.trim().is_empty() {
                continue;
            }
            definitions.push(Definition {
                definition,
                examples: strings(&sense["examples"]),
                example: strings(&sense["examples"])
                    .into_iter()
                    .next()
                    .unwrap_or_default(),
                synonyms: strings(&sense["synonyms"]),
                antonyms: strings(&sense["antonyms"]),
            });
        }
        if !definitions.is_empty() {
            meanings.push(Meaning {
                part_of_speech: text(&row["partOfSpeech"]),
                definitions,
                synonyms: strings(&row["synonyms"]),
                antonyms: strings(&row["antonyms"]),
            });
        }
    }
    if meanings.is_empty() {
        return Some(vec![]);
    }
    Some(vec![Entry {
        word: word.into(),
        phonetic: phonetics
            .first()
            .map(|p| p.text.clone())
            .unwrap_or_default(),
        phonetics,
        meanings,
    }])
}
#[cfg(test)]
mod tests {
    use super::*;
    use serde_json::json;
    #[test]
    fn maps_secondary_and_tertiary_contracts() {
        let entry = normalize(1, "hello", json!({"entries":[{"language":{"code":"en"},"partOfSpeech":"noun","pronunciations":[{"text":"ipa"}],"synonyms":["greeting"],"senses":[{"definition":"A greeting.","examples":["Hello there."]}]}]})).unwrap();
        assert_eq!(entry[0].phonetic, "ipa");
        assert_eq!(entry[0].meanings[0].definitions[0].example, "Hello there.");
        let entry = normalize(2, "hello", json!({"meanings":[{"partOfSpeech":"noun","senses":[{"glosses":["A greeting."],"synonyms":["hi"]}]}]})).unwrap();
        assert_eq!(
            entry[0].meanings[0].definitions[0].definition,
            "A greeting."
        );
        assert!(normalize(1, "hello", json!({"broken":true})).is_none());
        assert!(
            normalize(2, "hello", json!({"meanings":[]}))
                .unwrap()
                .is_empty()
        );
    }
}
