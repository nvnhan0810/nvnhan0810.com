use crate::application::ports::PortFuture;

pub trait CorsOriginRepository: Send + Sync {
    fn allows_origin<'a>(&'a self, origin: &'a str) -> PortFuture<'a, bool>;
}
