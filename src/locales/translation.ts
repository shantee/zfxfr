import YukinaConfig from "../../yukina.config";
import type I18nKeys from "./keys";
import { en } from "./languages/en";
import { fr } from "./languages/fr";

export type Translation = { [K in I18nKeys]: string };

const map: Record<string, Translation> = {
  en,
  "en-us": en,
  "en_gb": en,

  fr,
  "fr-fr": fr,
  "fr_fr": fr,
};

export function getTranslation(lang: string): Translation {
  return map[(lang || "en").toLowerCase()] || en;
}

export function i18n(key: I18nKeys, ...interpolations: string[]): string {
  const lang = YukinaConfig.locale;
  let translation = getTranslation(lang)[key];
  for (const arg of interpolations) {
    translation = translation.replace("{{}}", arg);
  }
  return translation;
}
