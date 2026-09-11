import { useState } from "react";
import { BLOG_COPY } from "@/ts/constants/blogCopy";
import { Button } from "../ui/button";
import { Input } from "../ui/input";

type SearchFormProps = {
  onSearch: (search: string) => void;
};

const SearchForm = ({ onSearch }: SearchFormProps) => {
  const [search, setSearch] = useState("");

  const handleSearch = () => {
    onSearch(search);
  };

  return (
    <div className="flex items-center gap-2">
      <Input
        type="text"
        placeholder={BLOG_COPY.searchPlaceholder}
        name="search"
        className="w-full max-w-sm border-border bg-background focus-visible:ring-emerald-600"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        onKeyDown={(e) => e.key === "Enter" && handleSearch()}
      />
      <Button
        variant="outline"
        onClick={handleSearch}
        className="border-border hover:border-emerald-600/50 hover:bg-emerald-600/10 hover:text-emerald-500"
      >
        {BLOG_COPY.search}
      </Button>
    </div>
  );
};

export default SearchForm;
