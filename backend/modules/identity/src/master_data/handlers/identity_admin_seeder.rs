use crate::application::{
    error::Result, handlers::master_data_handler::create_client_if_missing,
    ports::MasterDataRepository,
};
use crate::master_data::data::identity_admin::identity_admin_input;

pub async fn run(
    repository: &dyn MasterDataRepository,
    portal_url: &str,
    redirect_uri: &str,
) -> Result<Option<String>> {
    let input = identity_admin_input(portal_url, redirect_uri)?;
    create_client_if_missing(repository, &input).await
}
