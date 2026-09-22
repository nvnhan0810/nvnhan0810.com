"use client";

import { CheckIcon, ChevronsUpDownIcon } from "lucide-react";
import { useId, useState } from "react";

import { Button } from "@/ts/components/ui/button";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/ts/components/ui/command";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/ts/components/ui/popover";
import { cn } from "@/ts/utils/index";

export type ComboboxOption = {
  value: string;
  label: string;
};

type Props = {
  options: ComboboxOption[];
  value: string;
  handleChange: (value: string) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  emptyText?: string;
  disabled?: boolean;
  className?: string;
  /** Higher z when rendered inside Dialog / fullscreen overlays */
  contentClassName?: string;
};

const Combobox = ({
  options,
  value,
  handleChange,
  placeholder = "Select...",
  searchPlaceholder = "Search...",
  emptyText = "No options found.",
  disabled = false,
  className,
  contentClassName,
}: Props): React.ReactElement => {
  const [open, setOpen] = useState(false);
  const listId = useId();
  const selected = options.find((option) => option.value === value);

  return (
    <Popover open={open} onOpenChange={setOpen} modal>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          role="combobox"
          aria-expanded={open}
          aria-controls={listId}
          disabled={disabled}
          className={cn(
            "w-full justify-between font-normal",
            !selected && "text-muted-foreground",
            className,
          )}
        >
          <span className="truncate">{selected?.label ?? placeholder}</span>
          <ChevronsUpDownIcon className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent
        id={listId}
        align="start"
        className={cn(
          "w-[var(--radix-popover-trigger-width)] p-0 z-[230]",
          contentClassName,
        )}
      >
        <Command>
          <CommandInput placeholder={searchPlaceholder} />
          <CommandList>
            <CommandEmpty>{emptyText}</CommandEmpty>
            <CommandGroup>
              {options.map((option) => (
                <CommandItem
                  key={option.value === "" ? "__empty" : option.value}
                  value={`${option.label} ${option.value}`}
                  keywords={[option.label, option.value]}
                  onSelect={() => {
                    handleChange(option.value);
                    setOpen(false);
                  }}
                >
                  <CheckIcon
                    className={cn(
                      "mr-2 h-4 w-4 shrink-0",
                      value === option.value ? "opacity-100" : "opacity-0",
                    )}
                  />
                  <span className="truncate">{option.label}</span>
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
};

export default Combobox;
