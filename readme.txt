=== T-Rent Admin App ===
Contributors: t-rent
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 2.3.0
License: Proprietary

Ett avgrenset WordPress-tillegg for T-Rents daglige utleiedrift.

== Funksjoner ==

* Utstyrskontroll etter utleie, inkludert status for kontroll, service og vedlikehold.
* Manuell blokkering og fjerning av utleiedatoer i RnB.
* Mobilvisning med faner for både utstyrskontroll og datoblokkering via samme sikre direktelenke.
* Søk etter utstyr på navn, utstyrs-ID, status eller ordrenummer.
* Søkbar utstyrsvelger ved manuell datoblokkering.
* Installerbar mobilapp (PWA) med eget appikon og isolert service worker.

== Avhengigheter ==

WooCommerce og WooCommerce Booking & Rental System (RnB) må allerede være installert og aktivert. Dette tillegget erstatter eller endrer ikke kildekoden til disse systemene.

== Mobilinstallasjon ==

Åpne den sikre direktelenken i Chrome. En eldre direktelenke videresendes automatisk til den isolerte appadressen. Når appen er klar, vises knappen «Installer app», og Chrome kan installere den i frittstående modus med eget ikon.

== Sikker aktivering ==

Tillegget oppretter ingen databasetabeller og endrer ingen priser, ordre, betalinger eller kundesider ved aktivering. En tilfeldig tilgangsnøkkel opprettes første gang en autorisert administrator åpner utstyrskontrollen. Utover dette lagres data bare når en autorisert bruker utfører en kontrollhandling eller legger til eller fjerner en manuell datoblokkering.
