const Footer = () => {
  return (
    <footer className="border-t border-border bg-muted/40">
      <div className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <p className="text-center text-sm text-muted-foreground">
          Copyright © {new Date().getFullYear()} - <a href="mailto:nguyenvannhan0810@gmail.com" className="hover:text-foreground transition-colors">nvnhan0810</a>
        </p>
      </div>
    </footer>
  );
};

export default Footer;
