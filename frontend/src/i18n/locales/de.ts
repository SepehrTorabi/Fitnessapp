import type { MessageSchema } from '../index'

/**
 * Deutsche Übersetzungen.
 *
 * Typisiert gegen MessageSchema, das aus en.ts abgeleitet wird: ein fehlender
 * oder falsch geschriebener Schlüssel ist damit ein TypeScript-Fehler und keine
 * englische Zeichenkette in der deutschen Oberfläche.
 *
 * Durchgehend geduzt - eine Ernährungs-App spricht die Person an, die sie
 * benutzt, nicht deren Vorgesetzte.
 */
const de: MessageSchema = {
  common: {
    save: 'Speichern',
    saving: 'Wird gespeichert…',
    cancel: 'Abbrechen',
    search: 'Suchen',
    searching: 'Wird gesucht…',
    loading: 'Wird geladen…',
    remove: 'Entfernen',
    add: 'Hinzufügen',
    saved: 'Gespeichert.',
    day: 'Tag',
    kcal: 'kcal',
    grams: 'g',
    optional: 'optional',
    none: '—',
  },

  nav: {
    brand: 'Fitnessapp',
    dashboard: 'Übersicht',
    diary: 'Essen erfassen',
    foods: 'Lebensmittel & Rezepte',
    profile: 'Körperdaten',
    settings: 'Einstellungen',
    signOut: 'Abmelden',
  },

  titles: {
    dashboard: 'Übersicht',
    diary: 'Essen erfassen',
    foods: 'Lebensmittel & Rezepte',
    profile: 'Deine Körperdaten',
    settings: 'Einstellungen',
    login: 'Anmelden',
    register: 'Konto erstellen',
    verify: 'Adresse wird bestätigt',
    notFound: 'Nicht gefunden',
  },

  auth: {
    emailLabel: 'E-Mail-Adresse',
    passwordLabel: 'Passwort',
    nameLabel: 'Dein Name',
    signIn: 'Anmelden',
    signingIn: 'Anmeldung läuft…',
    noAccount: 'Noch kein Konto?',
    createOne: 'Jetzt erstellen',
    haveAccount: 'Du hast schon ein Konto?',
    createAccount: 'Konto erstellen',
    creating: 'Wird erstellt…',
    passwordHint: 'Mindestens 10 Zeichen.',
    resendLink: 'Bestätigungslink erneut senden',
    unreachable: 'Der Server ist nicht erreichbar. Läuft die API?',
    sendFailed: 'Die E-Mail konnte nicht gesendet werden.',

    checkInbox: 'Schau in dein Postfach',
    confirmationSent:
      'Wir haben einen Bestätigungslink an {email} geschickt. Öffne ihn, um dein Konto zu aktivieren, und melde dich dann an.',
    mailpitHint: 'Läuft lokal? Die E-Mail liegt in Mailpit unter {link}.',
    backToSignIn: 'Zurück zur Anmeldung',

    confirming: 'Wird bestätigt…',
    oneMoment: 'Einen Moment.',
    allSet: 'Alles erledigt',
    linkFailed: 'Dieser Link hat nicht funktioniert',
    linkMissingToken: 'In diesem Link fehlt der Bestätigungscode.',
    verifyUnreachable: 'Der Server war nicht erreichbar, um deine Adresse zu bestätigen.',
    linkExpiredHint:
      'Bestätigungslinks laufen nach 24 Stunden ab und lassen sich nur einmal verwenden. Versuch dich anzumelden - ist das Konto noch unbestätigt, kannst du dort einen neuen Link anfordern.',
  },

  dashboard: {
    greeting: 'Hallo, {name}',
    eatenToday: 'Heute gegessen',
    burned: 'Durch Aktivität verbrannt',
    leftToday: 'Heute noch übrig',
    overBudget: 'Über dem Budget',
    budget: 'Budget {kcal} kcal',
    macrosToday: 'Nährwerte heute',
    targetExplain:
      'Ziel {target} kcal — Grundumsatz {bmr}, Gesamtumsatz {tdee}, berechnet mit {formula}.',
    todaysEntries: 'Einträge von heute',
    addSomething: 'Etwas hinzufügen',
    nothingToday: 'Heute noch nichts erfasst.',
    loadFailed: 'Deine Übersicht konnte nicht geladen werden.',
    averages:
      'An dem einen Tag, den du diese Woche erfasst hast, waren es im Schnitt {kcal} kcal und {protein} g Eiweiß. | An den {count} Tagen, die du diese Woche erfasst hast, waren es im Schnitt {kcal} kcal und {protein} g Eiweiß.',
    onboardingTitle: 'Noch ein Schritt',
    onboardingProfile:
      'Sag uns Alter, Größe und wie aktiv du bist, damit wir deinen Bedarf berechnen können.',
    onboardingWeight: 'Trag dein aktuelles Gewicht ein, damit wir deinen Tagesbedarf berechnen können.',
    onboardingButton: 'Körperdaten ausfüllen',
    tableFood: 'Lebensmittel',
    tableMeal: 'Mahlzeit',
    tableAmount: 'Menge',
    tableMacros: 'E / K / F',
    exportPdf: 'Als PDF herunterladen',
    exportHint: 'Alle erfassten Tage, im Layout des gedruckten Tagebuchs.',
  },

  chart: {
    title: 'Kalorien pro Tag',
    caption: 'Die Balken zeigen, was du gegessen hast; die Linie ist das Budget des Tages.',
    showTable: 'Als Tabelle',
    showChart: 'Als Diagramm',
    withinBudget: 'Im Budget',
    overBudget: 'Über dem Budget',
    budgetLine: 'Budget',
    hoverHint: 'Fahr über einen Tag für Details.',
    eaten: '{kcal} kcal gegessen',
    ofBudget: 'von {kcal} Budget',
    kcalLeft: '{kcal} kcal übrig',
    kcalOver: '{kcal} kcal darüber',
    noTarget: 'kein Ziel hinterlegt',
    ariaLabel: 'Gegessene Kalorien pro Tag im Vergleich zum Tagesbudget',
    tableDay: 'Tag',
    tableEaten: 'Gegessen',
    tableBudget: 'Budget',
    tableDifference: 'Differenz',
  },

  macros: {
    protein: 'Eiweiß',
    carbs: 'Kohlenhydrate',
    fat: 'Fett',
    overTarget: '{grams} g über dem Ziel',
  },

  diary: {
    findFood: 'Lebensmittel finden',
    searchPlaceholder: 'z. B. Haferflocken, Hähnchen, Banane',
    queryTooShort: 'Gib mindestens zwei Zeichen ein.',
    nothingFound:
      'Nichts gefunden. Du kannst dieses Lebensmittel unter „Lebensmittel & Rezepte“ selbst anlegen.',
    searchFailed: 'Die Suche ist fehlgeschlagen.',
    externalHeading: 'Aus Open Food Facts',
    perHundred: '{kcal} kcal / 100 g',
    importFailed: 'Das Produkt konnte nicht übernommen werden.',
    barcodeFailed: 'Der Barcode konnte nicht nachgeschlagen werden.',

    scanTitle: 'Produkt scannen',
    scanIntro: 'Scanne den Barcode einer Verpackung mit der Kamera oder tippe die Nummer ein.',
    openScanner: 'Scanner öffnen',

    yourRecipes: 'Deine Rezepte',
    servings: 'Portionen',
    kcalPerServing: '{kcal} kcal pro Portion',
    recipeFailed: 'Das Rezept konnte nicht erfasst werden.',

    amount: 'Menge',
    unit: 'Einheit',
    meal: 'Mahlzeit',
    addToDiary: 'Zum Tagebuch hinzufügen',
    adding: 'Wird hinzugefügt…',
    saveFailed: 'Der Eintrag konnte nicht gespeichert werden.',
    preview: '· {grams} g · {protein} g Eiweiß · {carbs} g Kohlenhydrate · {fat} g Fett',

    totals: 'Summe für diesen Tag',
    left: '{kcal} übrig',
    over: '{kcal} darüber',
    entries: 'Einträge',
    nothingLogged: 'Für diesen Tag ist nichts erfasst.',
    loadDayFailed: 'Dieser Tag konnte nicht geladen werden.',

    activity: 'Aktivität',
    activityIntro: 'Verbrannte Kalorien. Sie werden dem Budget des Tages gutgeschrieben.',
    activityWhat: 'Was hast du gemacht?',
    activityPlaceholder: '5 km laufen',
    activityKcal: 'Verbrannte Kalorien',
    activityMinutes: 'Minuten (optional)',
    addActivity: 'Aktivität hinzufügen',
    activityIncomplete: 'Beschreib die Aktivität und gib an, wie viele Kalorien sie verbrannt hat.',
    activityFailed: 'Die Aktivität konnte nicht gespeichert werden.',
    minutesShort: 'Min.',
  },

  scanner: {
    scan: 'Barcode scannen',
    stop: 'Scannen beenden',
    holdSteady: 'Halte den Barcode in den Rahmen.',
    manualPlaceholder: '…oder Barcode-Nummer eintippen',
    manualLabel: 'Barcode-Nummer',
    lookUp: 'Nachschlagen',
    tooShort: 'Ein Barcode hat mindestens 8 Ziffern.',
    unsupported: 'Dieser Browser kann die Kamera nicht nutzen. Tipp die Nummer stattdessen ein.',
    denied: 'Der Kamerazugriff wurde abgelehnt. Tipp die Nummer stattdessen ein.',
    failed: 'Die Kamera konnte nicht gestartet werden. Tipp die Nummer stattdessen ein.',
  },

  foods: {
    newFood: 'Neues Lebensmittel',
    newRecipe: 'Neues Rezept',
    defineFood: 'Lebensmittel anlegen',
    defineIntro:
      'Alle Werte pro 100 g (bzw. pro 100 ml bei Flüssigkeiten). Nach dem Speichern taucht es sofort in deiner Suche auf.',
    name: 'Name',
    brand: 'Marke (optional)',
    barcode: 'Barcode (optional)',
    calories: 'Kalorien (kcal)',
    protein: 'Eiweiß (g)',
    carbs: 'Kohlenhydrate (g)',
    fat: 'Fett (g)',
    fiber: 'Ballaststoffe (g, optional)',
    sugar: 'Zucker (g, optional)',
    density: 'Dichte (g pro ml)',
    densityHint:
      'Nur wichtig, wenn du das in Löffeln oder Millilitern misst. Wasser ist 1, Öl etwa 0,92, Honig etwa 1,42.',
    portions: 'Benannte Portionen (optional)',
    addPortion: 'Portion hinzufügen',
    portionsHint:
      'Wie viel eine Scheibe, ein Stück oder ein Löffel davon wiegt. Ohne diese Angaben kannst du es nur nach Gewicht oder Volumen erfassen.',
    portionLabelPlaceholder: 'Scheibe',
    portionGramsPlaceholder: 'Gramm',
    saveFood: 'Lebensmittel speichern',
    foodSaved: '„{label}“ ist jetzt in deinem Katalog.',
    saveFoodFailed: 'Das Lebensmittel konnte nicht gespeichert werden.',
    energyMismatch:
      'Diese Nährwerte ergeben rechnerisch etwa {implied} kcal, eingetragen hast du {stated}. Das ist einen zweiten Blick wert — meistens steckt ein Wert pro Portion statt pro 100 g dahinter.',

    buildRecipe: 'Rezept zusammenstellen',
    recipeIntro:
      'Ein Rezept bezieht seine Nährwerte aus seinen Zutaten. Korrigierst du später eine Zutat, stimmt jedes Rezept damit wieder.',
    recipeName: 'Name',
    recipeServings: 'Portionen',
    recipeNotes: 'Notizen (optional)',
    addIngredient: 'Zutat hinzufügen',
    searchYourFoods: 'Deine Lebensmittel durchsuchen',
    ingredient: 'Zutat',
    gramsColumn: 'Gramm',
    needIngredient: 'Füge mindestens eine Zutat hinzu.',
    recipeSaved: '„{name}“ gespeichert — {kcal} kcal pro Portion.',
    saveRecipeFailed: 'Das Rezept konnte nicht gespeichert werden.',
    sharePublicly: 'Dieses Rezept mit anderen teilen',
    saveRecipe: 'Rezept speichern',
    yourRecipes: 'Deine Rezepte',
    servingsColumn: 'Portionen',
    kcalPerServingColumn: 'kcal / Portion',
    perServingSummary:
      '{kcal} kcal pro Portion | · {grams} g · {protein} g Eiweiß · {carbs} g Kohlenhydrate · {fat} g Fett',
    wholeRecipe: 'Ganzes Rezept: {kcal} kcal, {grams} g',
  },

  profile: {
    aboutYou: 'Über dich',
    aboutIntro: 'Diese Angaben ändern sich selten, du füllst sie also nur einmal aus.',
    birthDate: 'Geburtsdatum',
    sex: 'Geschlecht',
    sexHint:
      'Die Kalorienformeln rechnen je Geschlecht mit einer anderen Konstante - nur deshalb wird danach gefragt.',
    height: 'Größe (cm)',
    activityQuestion: 'Wie aktiv bist du?',
    goalQuestion: 'Was ist dein Ziel?',
    saveFailed: 'Dein Profil konnte nicht gespeichert werden.',

    weighIn: 'Wiegen heute',
    weighInIntro:
      'Wiegst du dich zweimal am selben Tag, ersetzt das den früheren Eintrag, statt einen zweiten anzulegen.',
    weight: 'Gewicht (kg)',
    muscleMass: 'Muskelmasse (kg, optional)',
    fatMass: 'Fettmasse (kg, optional)',
    fatMassHint:
      'Kennst du deine Fettmasse, wechselt die Berechnung auf eine Formel auf Basis der fettfreien Masse - die ist genauer als eine auf Basis des Gesamtgewichts.',
    saveWeighIn: 'Wiegen speichern',
    weightRequired: 'Trag dein Gewicht ein.',
    weighInFailed: 'Das Wiegen konnte nicht gespeichert werden.',

    dailyTarget: 'Dein Tagesziel',
    noTargetYet: 'Trag deine Daten und ein Gewicht ein, dann erscheint hier dein Ziel.',
    bmr: 'Grundumsatz',
    tdee: 'Gesamtumsatz',
    proteinRow: 'Eiweiß',
    carbsRow: 'Kohlenhydrate',
    fatRow: 'Fett',
    calculatedWith: 'Berechnet mit der Formel {formula}.',
  },

  settings: {
    title: 'Einstellungen',
    intro: 'Diese Einstellungen hängen an deinem Konto und gelten auf jedem Gerät, an dem du dich anmeldest.',

    languageTitle: 'Sprache',
    languageIntro: 'Die Sprache der Oberfläche und der E-Mails, die wir dir schicken.',

    themeTitle: 'Darstellung',
    themeIntro: 'Hell, dunkel oder so, wie dein Gerät eingestellt ist.',
    themeSystem: 'Wie mein Gerät',
    themeLight: 'Hell',
    themeDark: 'Dunkel',
    themeSystemHint: 'Folgt deinem Betriebssystem und wechselt mit ihm.',

    saveFailed: 'Die Einstellung konnte nicht gespeichert werden.',
    offlineNote: 'Nur auf diesem Gerät gespeichert — der Server war nicht erreichbar.',
  },

  sex: {
    male: 'Männlich',
    female: 'Weiblich',
  },

  meal: {
    breakfast: 'Frühstück',
    lunch: 'Mittagessen',
    dinner: 'Abendessen',
    snack: 'Snack',
  },

  activityLevel: {
    sedentary: 'Sitzend — kaum Bewegung, Schreibtischjob',
    lightly_active: 'Leicht aktiv — 1–3 Tage pro Woche Sport',
    moderately_active: 'Mäßig aktiv — 3–5 Tage pro Woche Sport',
    very_active: 'Sehr aktiv — 6–7 Tage pro Woche hartes Training',
    extra_active: 'Extrem aktiv — körperliche Arbeit oder zweimal täglich Training',
  },

  goal: {
    lose_weight: 'Abnehmen — 20 % unter dem Bedarf',
    maintain_weight: 'Gewicht halten',
    gain_muscle: 'Muskeln aufbauen — 10 % über dem Bedarf',
  },

  formula: {
    'katch-mcardle': 'Katch-McArdle',
    'mifflin-st-jeor': 'Mifflin-St Jeor',
  },

  notFound: {
    title: 'Seite nicht gefunden',
    body: 'Diese Adresse führt nirgendwohin.',
    back: 'Zurück zur Übersicht',
  },

  errors: {
    authentication_required: 'Dafür musst du angemeldet sein.',
    authentication_failed: 'E-Mail-Adresse oder Passwort stimmen nicht.',
    access_denied: 'Das darfst du nicht.',
    email_taken: 'Mit dieser E-Mail-Adresse gibt es schon ein Konto. Melde dich stattdessen an.',
    invalid_token: 'Dieser Bestätigungslink ist ungültig oder abgelaufen.',
    too_many_requests: 'Zu viele Versuche. Warte kurz und probier es noch einmal.',
    query_too_short: 'Gib mindestens zwei Zeichen ein, um zu suchen.',
    barcode_not_found:
      'Zu diesem Barcode wurde kein Produkt gefunden. Du kannst es als neues Lebensmittel anlegen.',
    barcode_taken: 'Ein Lebensmittel mit diesem Barcode ist bereits in der Datenbank.',
    food_not_found: 'Dieses Lebensmittel gibt es nicht.',
    recipe_not_found: 'Dieses Rezept gibt es nicht.',
    entry_not_found: 'Diesen Eintrag gibt es nicht.',
    activity_not_found: 'Diese Aktivität gibt es nicht.',
    import_failed:
      'Das Produkt konnte nicht übernommen werden. Vielleicht wurde es in der Quelldatenbank entfernt.',
    unresolvable_portion:
      'Diese Menge lässt sich für dieses Lebensmittel nicht umrechnen. Gib sie stattdessen in Gramm an.',
    invalid_target: 'Schick entweder ein Lebensmittel oder ein Rezept - nicht beides und nicht keines.',
    http_error: 'Etwas ist schiefgelaufen. Bitte versuch es noch einmal.',
  },
}

export default de
