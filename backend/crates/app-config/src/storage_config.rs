use std::{io, path::PathBuf};

use crate::ConfigSource;

#[derive(Debug, Clone)]
pub struct StorageConfig {
    pub public_dir: PathBuf,
    pub private_dir: PathBuf,
}

impl StorageConfig {
    pub fn from_source(source: &ConfigSource) -> io::Result<Self> {
        let value = source
            .required("STORAGE_DIR")
            .map_err(|message| io::Error::new(io::ErrorKind::InvalidInput, message))?;

        let root = PathBuf::from(value);

        if !root.is_absolute() {
            return Err(io::Error::new(
                io::ErrorKind::InvalidInput,
                "STORAGE_DIR must be a non-empty absolute path",
            ));
        }

        Ok(Self {
            public_dir: root.join("public"),
            private_dir: root.join("private"),
        })
    }
}
#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn derives_separate_roots_from_absolute_storage_path() {
        let source =
            ConfigSource::from_values([("STORAGE_DIR".into(), "/srv/test storage".into())]);
        let config = StorageConfig::from_source(&source).unwrap();
        assert_eq!(config.public_dir, PathBuf::from("/srv/test storage/public"));
        assert_eq!(
            config.private_dir,
            PathBuf::from("/srv/test storage/private")
        );
    }

    #[test]
    fn rejects_missing_blank_and_relative_storage_paths() {
        assert!(StorageConfig::from_source(&ConfigSource::from_values([])).is_err());
        for value in ["", "  ", "storage", "./storage", "../storage"] {
            let source = ConfigSource::from_values([("STORAGE_DIR".into(), value.into())]);
            assert_eq!(
                StorageConfig::from_source(&source).unwrap_err().kind(),
                io::ErrorKind::InvalidInput
            );
        }
    }
}
