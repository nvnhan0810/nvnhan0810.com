import { useTranslation } from "@/ts/providers/i18n-provider";
import { useState } from "react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";

type SearchFormProps = {
  onSearch: (search: string) => void;
};

const SearchForm = ({ onSearch }: SearchFormProps) => {
  const { t } = useTranslation();
  const [search, setSearch] = useState("");

  const handleSearch = () => {
    onSearch(search);
  };

  return (
    <div className="flex items-center gap-2">
      <Input
        type="text"
        placeholder={t("blog.searchPlaceholder")}
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
        {t("blog.search")}
      </Button>
    </div>
  );
};

export default SearchForm;
