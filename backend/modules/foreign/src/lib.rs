pub mod application;
pub mod config;
pub mod domain;
pub mod infrastructure;
pub mod presentation;

mod runtime;

pub use presentation::http::routes::foreign_routes;
pub use runtime::{Foreign, ForeignShutdown, ForeignWorker, start};
