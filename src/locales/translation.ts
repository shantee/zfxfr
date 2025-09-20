import YukinaConfig from "../../yukina.config";
import type { I18nKeys } from "./keys";
import { en } from "./languages/en";
import { fr } from "./languages/fr";

export type Translation = { [K in I18nKeys]: string };

const map: Record<string, Translation> = { en, fr };
const DEFAULT_LOCALE = (YukinaConfig.locale || "fr").toLowerCase(); // ← fr par défaut

export function getTranslation(lang: string): Translation {
  const key = (lang || DEFAULT_LOCALE).toLowerCase();
  return map[key] ?? map[DEFAULT_LOCALE] ?? fr; // ← fallback fr
}

export function i18n(key: I18nKeys, ...xs: string[]) {
  let t = getTranslation(YukinaConfig.locale)[key];
  xs.forEach((x) => (t = t.replace("{{}}", x)));
  return t;
}
