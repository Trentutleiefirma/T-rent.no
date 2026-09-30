# T-Rent Admin App

Mobilvennlig front-end for å administrere T-Rent uten å bruke wp-admin.

## Versjon 0.2.0

Appen samler nå fire hovedområder på `/t-rent-app/`:

### Bookinger
- aktive, kommende og avsluttede RnB-bookinger
- produkt og ordrenummer
- **leie fra / til**
- **dato og klokkeslett bookingen ble opprettet**
- **kundenavn, telefon og e-post**
- ordrestatus, beløp og betalingsmåte
- telefonnummer er klikkbart på mobil
- e-post er klikkbar

### Blokker dato
Samme RnB-logikk som T-Rent Datoblokkering i «Utleie system»:
- velg utleieprodukt
- velg fra- og til-dato
- samme dato i begge felt = én hel blokkert dag
- blokkering skrives til `rnb_availability` som `CUSTOM`
- blokkering gjelder alle inventory-poster som er knyttet til produktet
- aktive blokkeringer kan fjernes fra appen
- WooCommerce/LiteSpeed produktcache tømmes etter endring

### Utstyr
Samme statusdata som T-Rent Utstyrskontroll:
- Må kontrolleres
- Service
- Ikke klar
- Ute på leie
- Kontrollert og klar
- sist kontrollert
- sist service
- siste / pågående leie
- neste booking
- notat kan lagres ved statusendring
- WooCommerce-retur gjør utstyret kontrollpliktig etter samme logikk som eksisterende kontroll-plugin

### Produkter
- produktsøk
- produktnavn
- publiseringsstatus
- depositum av/på
- depositumbeløp
- ordinær WooCommerce-grunnpris for ikke-RnB-produkter
- RnB-leiepris er fortsatt låst til vi kobler den korrekt mot RnB inventory/prisdata

## Sikkerhet
- krever innlogget WordPress-bruker med WooCommerce-produktrettigheter
- REST-kall bruker WordPress REST nonce
- app-siden er noindex/nofollow og no-cache
- ingen WooCommerce- eller RnB-kjernefiler endres av appen

## Installering
Denne branchen skal gjennomgås før live-installasjon. Når den er godkjent kopieres hele mappen `t-rent-admin-app` til `wp-content/plugins/`, pluginet aktiveres og appen åpnes på `/t-rent-app/`.


## Felles søk
Ett søkefelt øverst følger aktiv fane:
- Bookinger: produkt, ordre, kunde, telefon, e-post, leiedato, bookingdato, status og betalingsmåte
- Blokkeringer: produkt, produkt-ID og dato
- Utstyr: navn, status, kontroll/service, notater, ordrenummer og bookingdatoer
- Produkter: produktnavn via WooCommerce-søket

Søket filtrerer bare visningen og endrer ingen data.
