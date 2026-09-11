import type { SharedSeo } from "@/ts/types/seo";
import {
  OG_IMAGE_HEIGHT,
  OG_IMAGE_WIDTH,
  resolveAbsoluteUrl,
  truncateDescription,
} from "@/ts/utils/seo";
import { Head, usePage } from "@inertiajs/react";

type Props = {
  title: string;
  description: string;
  url: string;
  imageUrl?: string;
  type?: "website" | "article";
  locale?: string;
  publishedAt?: string;
  imageAlt?: string;
};

const SeoHead = ({
  title,
  description,
  url,
  imageUrl,
  type = "website",
  locale = "en_US",
  publishedAt,
  imageAlt,
}: Props) => {
  const { seo } = usePage<{ seo: SharedSeo }>().props;
  const safeDescription = truncateDescription(description);
  const absoluteUrl = resolveAbsoluteUrl(url, seo.siteUrl);
  const absoluteImage = resolveAbsoluteUrl(
    imageUrl ?? seo.defaultOgImage,
    seo.siteUrl
  );
  const alt = imageAlt ?? title;

  return (
    <Head title={title}>
      <meta head-key="description" name="description" content={safeDescription} />
      <link head-key="canonical" rel="canonical" href={absoluteUrl} />

      <meta head-key="og:type" property="og:type" content={type} />
      <meta head-key="og:site_name" property="og:site_name" content={seo.siteName} />
      <meta head-key="og:title" property="og:title" content={title} />
      <meta
        head-key="og:description"
        property="og:description"
        content={safeDescription}
      />
      <meta head-key="og:url" property="og:url" content={absoluteUrl} />
      <meta head-key="og:locale" property="og:locale" content={locale} />
      <meta head-key="og:image" property="og:image" content={absoluteImage} />
      <meta
        head-key="og:image:secure_url"
        property="og:image:secure_url"
        content={absoluteImage}
      />
      <meta
        head-key="og:image:width"
        property="og:image:width"
        content={String(OG_IMAGE_WIDTH)}
      />
      <meta
        head-key="og:image:height"
        property="og:image:height"
        content={String(OG_IMAGE_HEIGHT)}
      />
      <meta head-key="og:image:alt" property="og:image:alt" content={alt} />

      <meta
        head-key="twitter:card"
        name="twitter:card"
        content="summary_large_image"
      />
      {seo.twitterSite ? (
        <meta head-key="twitter:site" name="twitter:site" content={seo.twitterSite} />
      ) : null}
      <meta head-key="twitter:title" name="twitter:title" content={title} />
      <meta
        head-key="twitter:description"
        name="twitter:description"
        content={safeDescription}
      />
      <meta head-key="twitter:image" name="twitter:image" content={absoluteImage} />
      <meta head-key="twitter:image:alt" name="twitter:image:alt" content={alt} />

      {type === "article" && publishedAt ? (
        <meta
          head-key="article:published_time"
          property="article:published_time"
          content={publishedAt}
        />
      ) : null}
    </Head>
  );
};

export default SeoHead;
