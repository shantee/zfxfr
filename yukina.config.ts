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
      nameKey: I18nKeys.nav_bar_archive,
      href: "/archive",
    },
    {
      nameKey: I18nKeys.nav_bar_contact,
      href: "/contact",
    },
    {
      nameKey: I18nKeys.nav_bar_blog,
      href: "/blog",
    },
  ],

  username: "shantee",
  sign: "Ad Astra Per Aspera.",
  avatarUrl: "/images/avatar1.jpg",
  socialLinks: [
    {
      icon: "line-md:github-loop",
      link: "https://github.com/WhitePaper233",
    },
    {
      icon: "mingcute:bilibili-line",
      link: "https://space.bilibili.com/22433608",
    },
    {
      icon: "mingcute:netease-music-line",
      link: "https://music.163.com/#/user/home?id=125291648",
    },
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
