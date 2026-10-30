use std::{collections::HashMap, env, path::Path};

/// Immutable startup snapshot; process environment overrides .env values.
/// Reading dotenv into a map avoids mutating process environment in async code.
pub struct ConfigSource(HashMap<String, String>);

impl ConfigSource {
    pub fn load() -> Result<Self, String> {
        let path = Path::new(".env");
        let mut values = if path.is_file() {
            dotenvy::from_path_iter(path)
                .map_err(|_| "Cannot read .env".to_owned())?
                .collect::<Result<HashMap<_, _>, _>>()
                .map_err(|_| "Invalid .env syntax".to_owned())?
        } else {
            HashMap::new()
        };
        values.extend(env::vars());
        Ok(Self(values))
    }

    pub fn required(&self, name: &str) -> Result<String, String> {
        self.0
            .get(name)
            .filter(|v| !v.trim().is_empty())
            .cloned()
            .ok_or_else(|| format!("Missing configuration: {name}"))
    }
    pub fn optional(&self, name: &str) -> Option<&str> {
        self.0.get(name).map(String::as_str)
    }
    #[cfg(test)]
    pub fn fixture(values: impl IntoIterator<Item = (String, String)>) -> Self {
        Self(values.into_iter().collect())
    }
}
