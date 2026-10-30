/// Only the verified Google identity may establish this account.
pub const SYSTEM_ROOT_EMAIL: &str = "nguyenvannhan0810@gmail.com";

pub fn is_system_root(email: &str) -> bool {
    email.eq_ignore_ascii_case(SYSTEM_ROOT_EMAIL)
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn root_match_is_exact_and_case_insensitive() {
        assert!(is_system_root("NguyenVanNhan0810@gmail.com"));
        assert!(!is_system_root("nguyenvannhan0810+other@gmail.com"));
        assert!(!is_system_root(
            "nguyenvannhan0810@gmail.com.attacker.example"
        ));
    }
}
