import I18nKeys from "./src/locales/keys";
import type { Configuration } from "./src/types/config";

const YukinaConfig: Configuration = {
  title: "zFX",
  subTitle: "Service dépannage",
  brandTitle: "zFX",

  description: "zFX : services, dépannage , coaching numérique",

  site: "https://zfx.fr/",

  locale: "fr-FR", // set for website language and date format

  navigators: [
    {
      nameKey: I18nKeys.nav_bar_home,
      href: "/",
    },
    {
      // Services + sous-menus
      nameKey: I18nKeys.nav_bar_services,
      href: "/services/",
      children: [
        { nameKey: I18nKeys.nav_bar_cours_informatique, href: "/services/cours-informatique/" },
        { nameKey: I18nKeys.nav_bar_formations, href: "/services/formations/" },
        { nameKey: I18nKeys.nav_bar_assistance_numerique, href: "/services/assistance-numerique/" },
        { nameKey: I18nKeys.nav_bar_coaching_digital, href: "/services/coaching-digital/" },
        { nameKey: I18nKeys.nav_bar_tarifs, href: "/tarifs/" },
      ],
    },
    {
      nameKey: I18nKeys.nav_bar_contact,
      href: "/contact/",
    },

    {
      nameKey: I18nKeys.nav_bar_presentation,
      href: "/presentation/",
    },

    {
      nameKey: I18nKeys.nav_bar_blog,
      href: "/blog/",
      children: [
        { nameKey: I18nKeys.nav_bar_archive, href: "/archive/" },
      ],
    },
  ],

  username: "shantee",
  sign: "I make stuff.",
  avatarUrl: "/images/avatar1.png",
  socialLinks: [
    { icon: "line-md:facebook", link: "https://www.facebook.com/zfx.informatique" },
    { icon: "line-md:github-loop", link: "https://github.com/shantee" },
    { icon: "line-md:youtube", link: "https://www.youtube.com/@limace" },
  ],
  maxSidebarCategoryChip: 6,
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

  bannerStyle: "LOOP", // 'loop' | 'static' | 'hidden'
};

export default YukinaConfig;
