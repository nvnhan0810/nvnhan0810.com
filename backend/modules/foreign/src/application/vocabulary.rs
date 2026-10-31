use crate::domain::dictionary::{Entry, normalize_word};
pub fn validate(entry: &mut Entry) -> Result<(), &'static str> {
    entry.word = normalize_word(&entry.word).ok_or("invalid_word")?;
    if entry.meanings.is_empty() || entry.meanings.len() > 100 {
        return Err("invalid_meanings");
    }
    let mut count = 0;
    for meaning in &entry.meanings {
        if meaning.definitions.is_empty() {
            return Err("invalid_meanings");
        }
        for definition in &meaning.definitions {
            count += 1;
            if definition.definition.trim().is_empty() {
                return Err("invalid_meanings");
            }
        }
    }
    if count > 200
        || serde_json::to_vec(entry)
            .map_err(|_| "invalid_entry")?
            .len()
            > 256 * 1024
    {
        return Err("entry_too_large");
    }
    Ok(())
}
