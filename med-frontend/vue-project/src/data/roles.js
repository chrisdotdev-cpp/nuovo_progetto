/**
 * Configurazione dei tre ruoli: card della home, schermata di login e rotte.
 * I path devono restare allineati ai moduli del router (/paziente, /medico, /admin).
 */
export const roles = {
  paziente: {
    title: "Paziente",
    about: "Prenota visite, consulta referti e gestisci il tuo profilo sanitario",
    icon: "fa-solid fa-user",
    iconBg: "bg-green-500",
    loginTitle: "Accedi come Paziente",
    dashboard: "/paziente/panoramica",
    notifiche: "/paziente/notifiche"
  },

  medico: {
    title: "Medico",
    about: "Gestisci agenda, cartelle cliniche e teleconsulti con i pazienti",
    icon: "fa-solid fa-user-doctor",
    iconBg: "bg-blue-500",
    loginTitle: "Accedi come Medico",
    dashboard: "/medico/panoramica",
    notifiche: "/medico/notifiche"
  },

  admin: {
    title: "Amministrazione",
    about: "Gestisci personale, finanze, magazzino e reportistica completa",
    icon: "fa-solid fa-user-shield",
    iconBg: "bg-purple-500",
    loginTitle: "Accedi come Amministratore",
    dashboard: "/admin/panoramica",
    notifiche: "/admin/notifiche"
  }
}

/** Ruoli validi: usato per proteggere la rotta /login/:role da valori inventati. */
export const validRoles = Object.keys(roles)
