use crate::application::{error::Result, models::ClientInput, ports::MasterDataRepository};
use uuid::Uuid;

/// Validate master data through the same domain rules as client administration.
pub async fn create_client_if_missing(
    repository: &dyn MasterDataRepository,
    input: &ClientInput,
) -> Result<Option<String>> {
    let id = Uuid::new_v4();
    super::client_handler::validate(id, input, None)?;
    repository.insert_client_if_missing(id, input).await
}
