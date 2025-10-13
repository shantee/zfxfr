import type I18nKeys from "../locales/keys";

/** Menu item + sous-menus récursifs */
interface Navigator {
  nameKey: I18nKeys;
  href: string;
  children?: Navigator[]; // <-- sous-menus optionnels
}

interface Configuration {
  title: string;
  subTitle: string;
  brandTitle: string;

  description: string;

  site: string;

  locale: "en" | "fr-FR";

  /** passe de {nameKey, href}[] à Navigator[] */
  navigators: Navigator[];

  username: string;
  sign: string;
  avatarUrl: string;

  socialLinks: { icon: string; link: string }[];

  maxSidebarCategoryChip: number;
  maxSidebarTagChip: number;
  maxFooterCategoryChip: number;
  maxFooterTagChip: number;

  banners: string[];

  slugMode: "HASH" | "RAW";

  license: {
    name: string;
    url: string;
  };

  bannerStyle: "LOOP";
}

export type { Configuration, Navigator };
