"use client";

import type { PaginationInfo } from "@/ts/types/common";
import { Pagination, PaginationContent, PaginationEllipsis, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from "../ui/pagination";

type PaginationBarProps = {
  pagination: PaginationInfo;
};

const PaginationBar = ({ pagination }: PaginationBarProps) => {
  const { current_page, links } = pagination;

  const getPageHref = (page: number) => {
    const safePage =
      page < 1 ? 1 : page > pagination.last_page ? pagination.last_page : page;
    if (typeof window === "undefined") {
      return `?page=${safePage}`;
    }
    return `${window.location.pathname}?page=${safePage}`;
  }

  return (
    <Pagination>
      <PaginationContent>
        <PaginationItem>
          <PaginationPrevious href={getPageHref(current_page - 1)} isActive={current_page > 1} />
        </PaginationItem>

        {links.slice(1, -1).map((link, index) => {
          if (link.label === "...") {
            return (
              <PaginationItem key={`ellipsis-${link.url ?? index}`}>
                <PaginationEllipsis />
              </PaginationItem>
            );
          }

          const page = Number.parseInt(link.label, 10);
          if (Number.isNaN(page)) return null;

          return (
            <PaginationItem key={page}>
              <PaginationLink href={getPageHref(page)} isActive={page === current_page}>{page}</PaginationLink>
            </PaginationItem>
          );
        })}
        <PaginationItem>
          <PaginationNext href={getPageHref(current_page + 1)} isActive={current_page < pagination.last_page} />
        </PaginationItem>
      </PaginationContent>
    </Pagination>
  );
};

export default PaginationBar;
