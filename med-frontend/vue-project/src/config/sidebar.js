// Una sola sidebar, ma con un array dinamico che cambia in base al ruolo.

export const sidebarLinks = {
  paziente: [
    { to: "/paziente/panoramica", icon: "fa-regular fa-user", label: "Panoramica" },
    { to: "/paziente/appuntamenti", icon: "fa-regular fa-pen-to-square", label: "Appuntamenti" },
    { to: "/paziente/cartella-clinica", icon: "fa-regular fa-clipboard", label: "Cartella Clinica" },
    { to: "/paziente/telemedicina", icon: "fa-solid fa-tv", label: "Telemedicina" },
    { to: "/paziente/pagamenti", icon: "fa-regular fa-credit-card", label: "Pagamenti" },
    { to: "/paziente/notifiche", icon: "fa-regular fa-bell", label: "Notifiche" },
  ],

  medico: [
    { to: "/medico/panoramica", icon: "fa-solid fa-stethoscope", label: "Panoramica" },
    { to: "/medico/agenda", icon: "fa-regular fa-calendar", label: "Agenda" },
    { to: "/medico/pazienti", icon: "fa-regular fa-id-badge", label: "Pazienti" },
    { to: "/medico/telemedicina", icon: "fa-solid fa-tv", label: "Telemedicina" },
    { to: "/medico/prescrizioni", icon: "fa-solid fa-capsules", label: "Prescrizioni" },
    { to: "/medico/richieste", icon: "fa-regular fa-hand", label:"Richieste" },
    { to: "/medico/notifiche", icon: "fa-regular fa-bell", label: "Notifiche"},
  ],

  admin: [
    { to: "/admin/panoramica", icon: "fa-solid fa-chart-line", label: "Panoramica" },
    { to: "/admin/utenti", icon: "fa-regular fa-address-book", label: "Utenti" },
    { to: "/admin/personale", icon: "fa-solid fa-users", label: "Personale" },
    { to: "/admin/prenotazioni", icon: "fa-regular fa-calendar", label: "Prenotazioni" },
    { to: "/admin/documenti", icon: "fa-regular fa-file-zipper", label: "Documenti"},
    { to: "/admin/finanziario", icon: "fa-solid fa-hand-holding-dollar", label: "Finanziario"},
    { to: "/admin/farmacia", icon: "fa-regular fa-hospital", label: "Farmacia"},
    { to: "/admin/notifiche", icon: "fa-regular fa-bell", label: "Notifiche"},
  ],
}
