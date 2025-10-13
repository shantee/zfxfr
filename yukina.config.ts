import I18nKeys from "./src/locales/keys";
import type { Configuration } from "./src/types/config";

const YukinaConfig: Configuration = {
  title: "zFX",
  subTitle: "Service dépannage",
  brandTitle: "zFX",

  description: "zFX : services, dépannage , coaching numérique",

  site: "https://zfx.fr",

  locale: "fr-FR", // set for website language and date format

  navigators: [
    {
      nameKey: I18nKeys.nav_bar_home,
      href: "/",
    },
    {
      // NOUVEAU : menu Services + sous-menu Tarifs
      nameKey: I18nKeys.nav_bar_services,
      href: "/services",
      children: [
        { nameKey: I18nKeys.nav_bar_tarifs, href: "/tarifs" },
      ],
    },
    {
      nameKey: I18nKeys.nav_bar_contact,
      href: "/contact",
    },
    {
      nameKey: I18nKeys.nav_bar_blog,
      href: "/blog",
      children: [
        { nameKey: I18nKeys.nav_bar_archive, href: "/archive" }, // adapte l'URL si besoin
        // { nameKey: I18nKeys.nav_bar_tags, href: "/tags" },     // éventuels autres sous-liens
      ],
    },
  ],

  username: "shantee",
  sign: "I make stuff.",
  avatarUrl: "/images/avatar1.png",
  socialLinks: [
    {
      icon: "line-md:github-loop",
      link: "https://github.com/shantee",
    },
    {
      icon: "line-md:youtube",
      link: "https://www.youtube.com/@limace",
    }
  ],
  maxSidebarCategoryChip: 6, // It is recommended to set it to a common multiple of 2 and 3
  maxSidebarTagChip: 12,
  maxFooterCategoryChip: 6,
  maxFooterTagChip: 24,

  banners: [
    "/images/empty1.jpg",
    "/images/empty2.jpg",
    "/images/empty3.jpg",
    "/images/empty4.jpg",
    "/images/empty5.jpg",
    "/images/empty6.jpg",
  ],

  slugMode: "RAW", // 'RAW' | 'HASH'

  license: {
    name: "CC BY-NC-SA 4.0",
    url: "https://creativecommons.org/licenses/by-nc-sa/4.0/",
  },

  // WIP functions
  bannerStyle: "LOOP", // 'loop' | 'static' | 'hidden'
};

export default YukinaConfig;
