//! Server-owned rounds: answers and scoring never trust client-supplied answers.
use serde_json::{Value, json};
use std::time::{SystemTime, UNIX_EPOCH};
pub fn now() -> u64 {
    SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .unwrap_or_default()
        .as_secs()
}
fn text<'a>(value: &'a Value, key: &str) -> &'a str {
    value[key].as_str().unwrap_or("")
}
pub fn definition(entry: &Value) -> &str {
    entry["meanings"][0]["definitions"][0]["definition"]
        .as_str()
        .unwrap_or("")
}
pub fn reveal(entry: &Value) -> Value {
    let mut meanings = Vec::new();
    for meaning in entry["meanings"].as_array().into_iter().flatten() {
        for definition in meaning["definitions"].as_array().into_iter().flatten() {
            let terms = |key: &str| -> Vec<String> {
                meaning[key]
                    .as_array()
                    .into_iter()
                    .flatten()
                    .chain(definition[key].as_array().into_iter().flatten())
                    .filter_map(Value::as_str)
                    .map(str::to_owned)
                    .collect::<std::collections::BTreeSet<_>>()
                    .into_iter()
                    .collect()
            };
            meanings.push(json!({"part_of_speech":meaning["partOfSpeech"],"definition":definition["definition"],"examples":definition["examples"],"example":definition["example"],"synonyms":terms("synonyms"),"antonyms":terms("antonyms")}));
        }
    }
    let audio = entry["phonetics"]
        .as_array()
        .into_iter()
        .flatten()
        .find_map(|p| p["audio"].as_str().filter(|s| !s.is_empty()));
    json!({"word":entry["word"],"phonetic":entry["phonetic"],"audio_url":audio,"meanings":meanings,"examples":entry["examples"],"synonyms":entry["synonyms"],"antonyms":entry["antonyms"],"definition":definition(entry),"part_of_speech":entry["meanings"][0]["partOfSpeech"]})
}
/// Select the least-practised sense, randomizing ties. Store only that sense
/// in the private round so its clues, answers and review always agree.
pub fn select_meaning(entry: &Value, counts: &Value) -> Option<Value> {
    let mut senses = Vec::new();
    for meaning in entry["meanings"].as_array().into_iter().flatten() {
        for definition in meaning["definitions"].as_array().into_iter().flatten() {
            let Some(text) = definition["definition"]
                .as_str()
                .filter(|s| !s.trim().is_empty())
            else {
                continue;
            };
            let key = format!(
                "{}\n{}",
                meaning["partOfSpeech"].as_str().unwrap_or(""),
                text
            );
            let count = counts[&key].as_u64().unwrap_or(0);
            senses.push((count, key, meaning.clone(), definition.clone()));
        }
    }
    shuffle(&mut senses);
    let (_, key, mut meaning, definition) = senses.into_iter().min_by_key(|sense| sense.0)?;
    meaning["definitions"] = json!([definition]);
    let mut selected = entry.clone();
    selected["meanings"] = json!([meaning]);
    selected["_meaning_key"] = json!(key);
    Some(selected)
}

fn round_reveal(state: &Value, id: &Value, entry: &Value) -> Value {
    let details = reveal(entry);
    let meaning = &details["meanings"][0];
    let phonetic = entry["phonetic"]
        .as_str()
        .filter(|s| !s.is_empty())
        .or_else(|| {
            entry["phonetics"]
                .as_array()
                .into_iter()
                .flatten()
                .find_map(|p| p["text"].as_str().filter(|s| !s.is_empty()))
        });
    let rating = id
        .as_i64()
        .and_then(|id| state["memoryRatings"].get(id.to_string()))
        .unwrap_or(&entry["_memory_level"]);
    json!({"vocabulary_id":id,"word":entry["word"],"phonetic":phonetic,
        "definition":meaning["definition"],"part_of_speech":meaning["part_of_speech"],
        "meanings":[meaning],"memory_level":rating})
}

/// Only revealed words may be rated, including individual Word Search finds.
pub fn rate(state: &mut Value, id: i64, level: i64) -> Result<(), &'static str> {
    if !(1..=4).contains(&level) {
        return Err("Choose a memory level from 1 to 4.");
    }
    let revealed = if state["mode"] == "word-search" {
        state["foundIds"]
            .as_array()
            .is_some_and(|ids| ids.contains(&json!(id)))
    } else {
        !state["feedback"].is_null() && state["id"].as_i64() == Some(id)
    };
    if !revealed {
        return Err("Finish this word before rating it.");
    }
    if !state["memoryRatings"].is_object() {
        state["memoryRatings"] = json!({});
    }
    state["memoryRatings"][id.to_string()] = json!(level);
    Ok(())
}

pub fn start(mode: &str, entries: &[(i64, Value)], score: i64) -> Result<Value, &'static str> {
    let (id, entry) = entries
        .first()
        .ok_or("Save some words with meanings before playing.")?;
    let word = text(entry, "word").to_lowercase();
    let mut state = json!({"roundId":uuid::Uuid::new_v4().to_string(),"mode":mode,"id":id,"entry":entry,"answer":word,"score":score,"startedAt":now(),"wordStartedAt":now(),"feedback":null,"wasCorrect":null,"guesses":[],"letters":[],"foundIds":[],"foundCells":{},"hintAt":null});
    match mode {
        "quiz" => {
            if entries.len() < 4 {
                return Err("Save at least four words before starting Quiz.");
            }
            let to_definition = uuid::Uuid::new_v4().as_bytes()[0].is_multiple_of(2);
            let mut answer = if to_definition {
                definition(entry).to_owned()
            } else {
                word.clone()
            };
            let mut options: Vec<String> = entries
                .iter()
                .map(|(_, entry)| {
                    if to_definition {
                        definition(entry).to_owned()
                    } else {
                        text(entry, "word").to_owned()
                    }
                })
                .collect();
            options.sort();
            options.dedup();
            options.retain(|option| option != &answer);
            shuffle(&mut options);
            options.truncate(3);
            let mut kind = if to_definition {
                "word_to_definition"
            } else {
                "definition_to_word"
            };
            if options.len() < 3 {
                kind = "definition_to_word";
                answer = word.clone();
                options = entries
                    .iter()
                    .skip(1)
                    .take(3)
                    .map(|(_, entry)| text(entry, "word").to_owned())
                    .collect();
            }
            options.push(answer.clone());
            shuffle(&mut options);
            state["answer"] = json!(answer);
            state["question"] = json!({"vocabulary_id":id,"question_type":kind,"prompt":if kind=="word_to_definition" {word.as_str()}else{definition(entry)},"correct_answer":"","options":options});
        }
        "scramble" => {
            let mut letters: Vec<char> = word.chars().collect();
            shuffle(&mut letters);
            if letters.iter().collect::<String>() == word {
                letters.reverse();
            }
            state["puzzle"] = json!({"vocabulary_id":id,"word_length":word.len(),"scrambled":letters.iter().collect::<String>(),"hint_definition":definition(entry),"hint_part_of_speech":entry["meanings"][0]["partOfSpeech"]});
        }
        "wordle" => {
            let mut bank = serde_json::Map::new();
            for letter in word.chars() {
                let key = letter.to_string();
                let n = bank.get(&key).and_then(Value::as_i64).unwrap_or(0) + 1;
                bank.insert(key, json!(n));
            }
            for _ in 0..3 {
                let letter = ((b'a' + uuid::Uuid::new_v4().as_bytes()[0] % 26) as char).to_string();
                bank.entry(letter).or_insert(json!(1));
            }
            state["puzzle"] = json!({"vocabulary_id":id,"word_length":word.len(),"max_guesses":6,"keyboard_letters":bank});
        }
        "hangman" => {
            state["puzzle"] = json!({"vocabulary_id":id,"mask":vec![Value::Null;word.len()],"max_wrong":6,"wrong_count":0,"clue_definition":definition(entry),"clue_part_of_speech":entry["meanings"][0]["partOfSpeech"]});
        }
        "word-search" => {
            if entries.len() < 4 {
                return Err("Save at least four words of 3–8 letters for Word Search.");
            }
            let (grid, words, paths) = build_search(entries)?;
            state["puzzle"] = json!({"grid":grid,"words":words,"grid_size":8});
            state["paths"] = paths;
        }
        _ => return Err("Unknown activity"),
    }
    Ok(state)
}
type SearchParts = (Vec<Vec<String>>, Vec<Value>, Value);
fn build_search(entries: &[(i64, Value)]) -> Result<SearchParts, &'static str> {
    let directions = [
        (0, 1),
        (1, 0),
        (1, 1),
        (1, -1),
        (0, -1),
        (-1, 0),
        (-1, 1),
        (-1, -1),
    ];
    for _ in 0..20 {
        let mut grid = vec![vec![String::new(); 8]; 8];
        let mut words = Vec::new();
        let mut paths = serde_json::Map::new();
        for (id, entry) in entries.iter().take(5) {
            let word = text(entry, "word");
            let mut placed = false;
            for _ in 0..150 {
                let random = uuid::Uuid::new_v4();
                let bytes = random.as_bytes();
                let (dr, dc) = directions[usize::from(bytes[0]) % 8];
                let r = i32::from(bytes[1] % 8);
                let c = i32::from(bytes[2] % 8);
                let cells: Vec<_> = word
                    .chars()
                    .enumerate()
                    .map(|(i, letter)| (r + i as i32 * dr, c + i as i32 * dc, letter))
                    .collect();
                if cells.iter().any(|(r, c, l)| {
                    !(0..8).contains(r)
                        || !(0..8).contains(c)
                        || (!grid[*r as usize][*c as usize].is_empty()
                            && grid[*r as usize][*c as usize] != l.to_string())
                }) {
                    continue;
                }
                for (r, c, l) in &cells {
                    grid[*r as usize][*c as usize] = l.to_string();
                }
                let cells: Vec<_> = cells.iter().map(|(r, c, _)| json!({"r":r,"c":c})).collect();
                words.push(json!({"vocabulary_id":id,"word":"","length":word.len(),"definition":definition(entry),"part_of_speech":entry["meanings"][0]["partOfSpeech"]}));
                paths.insert(id.to_string(), json!({"cells":cells,"entry":entry}));
                placed = true;
                break;
            }
            if !placed {
                break;
            }
        }
        if paths.len() != entries.len().min(5) {
            continue;
        }
        for row in &mut grid {
            for cell in row {
                if cell.is_empty() {
                    *cell = ((b'a' + uuid::Uuid::new_v4().as_bytes()[0] % 26) as char).to_string();
                }
            }
        }
        return Ok((grid, words, json!(paths)));
    }
    Err("Could not build grid. Please retry.")
}
fn shuffle<T>(items: &mut [T]) {
    for i in (1..items.len()).rev() {
        items.swap(i, usize::from(uuid::Uuid::new_v4().as_bytes()[0]) % (i + 1));
    }
}
fn finish(state: &mut Value, correct: bool) {
    state["wasCorrect"] = json!(correct);
    state["feedback"] = json!(if correct {
        "Correct!"
    } else {
        "Review the word below and try again."
    });
    state["elapsedSeconds"] =
        json!(now().saturating_sub(state["startedAt"].as_u64().unwrap_or(now())));
    if correct {
        state["score"] = json!(state["score"].as_i64().unwrap_or(0) + 1);
    }
}
pub fn act(state: &mut Value, action: &str, data: &Value) -> Result<bool, &'static str> {
    if !state["feedback"].is_null() {
        return Ok(false);
    }
    let mode = text(state, "mode").to_owned();
    let answer = text(state, "answer").to_owned();
    if action == "hint" {
        let cooldown = if mode == "word-search" { 10 } else { 20 };
        if state["hintAt"]
            .as_u64()
            .is_some_and(|stamp| now().saturating_sub(stamp) < cooldown)
        {
            return Err("Wait before requesting another hint.");
        }
        state["hintAt"] = json!(now());
        if mode == "word-search" {
            if let Some((_, path)) = state["paths"]
                .as_object()
                .into_iter()
                .flatten()
                .find(|(id, _)| state["foundCells"].get(*id).is_none())
            {
                state["hintCell"] = path["cells"][0].clone();
            }
        } else {
            state["hint"] = json!({"definition":definition(&state["entry"]),"part_of_speech":state["entry"]["meanings"][0]["partOfSpeech"]});
        }
        return Ok(false);
    }
    match (mode.as_str(), action) {
        ("quiz", "answer") => {
            let choice = text(data, "choice");
            if !state["question"]["options"]
                .as_array()
                .is_some_and(|items| items.iter().any(|v| v.as_str() == Some(choice)))
            {
                return Err("Choose one of the available answers.");
            }
            finish(state, choice == answer);
        }
        ("scramble", "answer") => {
            finish(
                state,
                text(data, "answer").trim().eq_ignore_ascii_case(&answer),
            );
        }
        ("wordle", "guess") => {
            let guess = text(data, "guess").to_lowercase();
            if guess.len() != answer.len() || !guess.bytes().all(|b| b.is_ascii_alphabetic()) {
                return Err("Guess must match the word length.");
            }
            let mut usage = std::collections::HashMap::new();
            for letter in guess.chars() {
                let count = usage.entry(letter.to_string()).or_insert(0u64);
                *count += 1;
                if *count
                    > state["puzzle"]["keyboard_letters"][letter.to_string()]
                        .as_u64()
                        .unwrap_or(0)
                {
                    return Err("Use only the available letters.");
                }
            }
            let mut remaining = answer.as_bytes().to_vec();
            let mut states = vec!["absent"; answer.len()];
            for (i, b) in guess.bytes().enumerate() {
                if remaining[i] == b {
                    states[i] = "correct";
                    remaining[i] = 0;
                }
            }
            for (i, b) in guess.bytes().enumerate() {
                if states[i] != "correct"
                    && let Some(position) = remaining.iter().position(|v| *v == b)
                {
                    states[i] = "present";
                    remaining[position] = 0;
                }
            }
            let tiles: Vec<_> = guess
                .chars()
                .enumerate()
                .map(|(i, letter)| json!({"letter":letter.to_string(),"state":states[i]}))
                .collect();
            state["guesses"]
                .as_array_mut()
                .ok_or("Invalid round")?
                .push(json!({"tiles":tiles}));
            if guess == answer
                || state["guesses"]
                    .as_array()
                    .is_some_and(|rows| rows.len() >= 6)
            {
                finish(state, guess == answer);
            }
        }
        ("hangman", "guess") => {
            let letter = text(data, "letter").to_lowercase();
            if letter.len() != 1 || !letter.bytes().all(|b| b.is_ascii_alphabetic()) {
                return Err("Choose a letter.");
            }
            if state["letters"]
                .as_array()
                .is_some_and(|items| items.contains(&json!(letter)))
            {
                return Ok(false);
            }
            state["letters"]
                .as_array_mut()
                .ok_or("Invalid round")?
                .push(json!(letter));
            let letters = state["letters"].as_array().ok_or("Invalid round")?;
            let mask: Vec<_> = answer
                .chars()
                .map(|l| {
                    if letters.contains(&json!(l.to_string())) {
                        json!(l.to_string())
                    } else {
                        Value::Null
                    }
                })
                .collect();
            let wrong = letters
                .iter()
                .filter(|v| !answer.contains(v.as_str().unwrap_or("")))
                .count();
            let solved = mask.iter().all(|v| !v.is_null());
            state["puzzle"]["mask"] = json!(mask);
            state["puzzle"]["wrong_count"] = json!(wrong);
            if solved || wrong >= 6 {
                finish(state, solved);
            }
        }
        ("word-search", "find") => {
            let cells = data["cells"]
                .as_array()
                .ok_or("Select a word in the grid.")?;
            let found = state["paths"]
                .as_object()
                .into_iter()
                .flatten()
                .find(|(_, path)| {
                    let expected = path["cells"].as_array();
                    expected.is_some_and(|expected| {
                        expected == cells || expected.iter().rev().eq(cells.iter())
                    })
                })
                .map(|(id, path)| (id.clone(), path.clone()));
            let (id, path) = found.ok_or("No word matches this selection.")?;
            if state["foundCells"].get(&id).is_some() {
                return Ok(false);
            }
            state["foundCells"][&id] = path["cells"].clone();
            state["foundIds"]
                .as_array_mut()
                .ok_or("Invalid round")?
                .push(json!(id.parse::<i64>().map_err(|_| "Invalid word")?));
            if let Some(words) = state["puzzle"]["words"].as_array_mut() {
                for word in words {
                    if word["vocabulary_id"].as_i64() == id.parse::<i64>().ok() {
                        word["word"] = path["entry"]["word"].clone();
                    }
                }
            }
            if state["foundIds"].as_array().map(Vec::len)
                == state["paths"].as_object().map(serde_json::Map::len)
            {
                finish(state, true);
            }
        }
        _ => return Err("Unsupported action"),
    }
    Ok(!state["feedback"].is_null())
}
pub fn public(state: &Value, best: i64) -> Value {
    let resolved = !state["feedback"].is_null();
    let mut result = json!({"roundId":state["roundId"],"puzzle":state["puzzle"],"question":state["question"],"guesses":state["guesses"],"guessedLetters":state["letters"],"foundIds":state["foundIds"],"foundCells":state["foundCells"],"hint":state["hint"],"hintAt":state["hintAt"],"hintCell":state["hintCell"],"startedAt":state["startedAt"],"wordStartedAt":state["wordStartedAt"],"elapsedSeconds":state["elapsedSeconds"],"feedback":state["feedback"],"wasCorrect":state["wasCorrect"],"sessionCorrect":state["score"],"bestCorrect":best,"celebrateRecord":state["celebrateRecord"],"reveal":null});
    if state["mode"] == "word-search" {
        let reviews: Vec<_> = state["paths"]
            .as_object()
            .into_iter()
            .flatten()
            .filter(|(id, _)| state["foundCells"].get(*id).is_some())
            .map(|(id, path)| round_reveal(state, &json!(id.parse::<i64>().ok()), &path["entry"]))
            .collect();
        result["reviews"] = json!(reviews);
    }
    if resolved {
        if state["mode"] != "word-search" {
            result["reveal"] = round_reveal(state, &state["id"], &state["entry"]);
        }
        if !result["question"].is_null() {
            result["question"]["correct_answer"] = state["answer"].clone();
        }
    }
    result
}
#[cfg(test)]
mod tests {
    use super::*;
    fn entries() -> Vec<(i64, Value)> {
        ["apple","banana","orange","pear"].iter().enumerate().map(|(i,word)|(i as i64+1,json!({"word":word,"meanings":[{"partOfSpeech":"noun","definitions":[{"definition":format!("meaning of {word}")}]}]}))).collect()
    }
    #[test]
    fn answers_stay_private_and_scores_are_replay_safe() {
        for mode in ["quiz", "scramble", "wordle", "hangman", "word-search"] {
            let mut state = start(mode, &entries(), 0).unwrap();
            assert!(public(&state, 0)["reveal"].is_null());
            if mode == "quiz" {
                assert_eq!(public(&state, 0)["question"]["correct_answer"], "");
                let answer = state["answer"].clone();
                act(
                    &mut state,
                    "answer",
                    &json!({"choice":answer,"correct_answer":"forged"}),
                )
                .unwrap();
                assert_eq!(state["score"], 1);
                assert!(!act(&mut state, "answer", &json!({"choice":"meaning of apple"})).unwrap());
                assert_eq!(state["score"], 1);
            }
        }
    }
    #[test]
    fn practice_rotates_senses_and_reveals_only_the_selected_definition() {
        let entry = json!({"word":"bank","phonetics":[{"text":"/bæŋk/"}],
        "meanings":[
            {"partOfSpeech":"noun","definitions":[
                {"definition":"A financial institution", "example":"She visited the bank."},
                {"definition":"Land beside a river", "examples":["We sat on the river bank."]}
            ]},
            {"partOfSpeech":"verb","definitions":[{"definition":"To tilt an aircraft"}]}
        ]});
        let mut counts = json!({});
        let mut seen = std::collections::BTreeSet::new();
        for _ in 0..3 {
            let selected = select_meaning(&entry, &counts).unwrap();
            let key = selected["_meaning_key"].as_str().unwrap().to_owned();
            assert!(seen.insert(key.clone()));
            counts[&key] = json!(1);
            let mut state = start("scramble", &[(7, selected.clone())], 0).unwrap();
            assert!(public(&state, 0)["reveal"].is_null());
            act(&mut state, "answer", &json!({"answer":"bank"})).unwrap();
            let revealed = public(&state, 0)["reveal"].clone();
            assert_eq!(revealed["vocabulary_id"], 7);
            assert_eq!(revealed["phonetic"], "/bæŋk/");
            assert_eq!(revealed["meanings"].as_array().unwrap().len(), 1);
            assert_eq!(revealed["definition"], definition(&selected));
            assert_eq!(
                revealed["meanings"][0]["example"],
                selected["meanings"][0]["definitions"][0]["example"]
            );
            assert_eq!(
                revealed["meanings"][0]["examples"],
                selected["meanings"][0]["definitions"][0]["examples"]
            );
        }
        assert!(select_meaning(&json!({"meanings":[]}), &counts).is_none());
    }
    #[test]
    fn ratings_require_a_revealed_word_and_do_not_change_scores() {
        let mut state = start("scramble", &entries(), 0).unwrap();
        assert!(rate(&mut state, 1, 4).is_err());
        act(&mut state, "answer", &json!({"answer":"apple"})).unwrap();
        for level in [0, 5, i64::MAX] {
            assert!(rate(&mut state, 1, level).is_err());
        }
        assert!(rate(&mut state, 2, 4).is_err());
        rate(&mut state, 1, 4).unwrap();
        assert_eq!(public(&state, 1)["reveal"]["memory_level"], 4);
        rate(&mut state, 1, 1).unwrap();
        assert_eq!(public(&state, 1)["reveal"]["memory_level"], 1);
        assert_eq!(state["score"], 1);
    }
    #[test]
    fn word_search_reviews_and_ratings_follow_each_found_word() {
        let mut state = start("word-search", &entries(), 0).unwrap();
        let (id, path) = state["paths"].as_object().unwrap().iter().next().unwrap();
        let id = id.parse::<i64>().unwrap();
        let cells = path["cells"].clone();
        assert!(rate(&mut state, id, 2).is_err());
        act(&mut state, "find", &json!({"cells":cells})).unwrap();
        rate(&mut state, id, 2).unwrap();
        let result = public(&state, 0);
        assert_eq!(result["reviews"].as_array().unwrap().len(), 1);
        assert_eq!(result["reviews"][0]["vocabulary_id"], id);
        assert_eq!(result["reviews"][0]["memory_level"], 2);
        assert!(result["reveal"].is_null());
        assert!(state["feedback"].is_null());
    }
    #[test]
    fn wordle_does_not_overcount_duplicate_letters() {
        let mut state = start("wordle", &entries(), 0).unwrap();
        state["puzzle"]["keyboard_letters"]["a"] = json!(5);
        act(&mut state, "guess", &json!({"guess":"aaaaa"})).unwrap();
        let tiles = state["guesses"][0]["tiles"].as_array().unwrap();
        assert_eq!(tiles.iter().filter(|t| t["state"] != "absent").count(), 1);
    }
}
