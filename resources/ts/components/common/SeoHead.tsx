import { Head } from "@inertiajs/react";

type Props = {
  title: string;
  description: string;
  url: string;
  imageUrl?: string;
  type?: "website" | "article";
};

const SeoHead = ({
  title,
  description,
  url,
  imageUrl = "/favicon.ico",
  type = "website",
}: Props) => {
  return (
    <Head title={title}>
      <meta name="description" content={description} />
      <link rel="canonical" href={url} />

      <meta property="og:type" content={type} />
      <meta property="og:title" content={title} />
      <meta property="og:description" content={description} />
      <meta property="og:url" content={url} />
      <meta property="og:image" content={imageUrl} />

      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={title} />
      <meta name="twitter:description" content={description} />
      <meta name="twitter:image" content={imageUrl} />
    </Head>
  );
};

export default SeoHead;

