// Användarens föredragna språk: det översta i webbläsarens "Web site
// language"-lista, samma lista som skickas som Accept-Language.
export function userLocale() {
  return navigator.languages?.[0] ?? navigator.language ?? 'sv-SE';
}
