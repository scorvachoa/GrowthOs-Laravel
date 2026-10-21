export const localeLabels = {
    es: 'ES',
    en: 'EN',
    pt: 'PT',
    fr: 'FR',
    de: 'DE',
    it: 'IT',
    ja: 'JA',
    ko: 'KO',
    zh: 'ZH',
}

export function localeLabel(locale) {
    return localeLabels[locale] || (locale || 'es').toUpperCase()
}
