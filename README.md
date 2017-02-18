# Schach REST Api

Diese Applikation stellt eine RESTful Schach API zur Verfügung, über welche man 
Spieler (User) und Partien (Matches) verwalten kann.

Die Applikation stellt einen HTML und einen JSON-View für die verwalteten Daten zur Verfügung.
Akzeptiert der rufende Client `application/json`, werden entsprechend JSON Daten zurück geliefert.
Wird kein entsprechender Header gesendet, so liefert die Applikation `text/html`.

Die Applikation kümmert sich (explizit) nicht um Authentifizierung oder Autorisierung.
Dies müsste ggf. durch einen dazwischen geschalteten Server (Middleware) oder ähnliches geschehen.

Das zurückgegebene JSON-Format orientiert sich an der HATEOAS Definition.


## Anforderungen
- PHP >=5.4

## Installation

* Installieren der Abhängigkeiten durch Ausführen des `install-vendors.sh` Skriptes
* Apache auf das `web/` Verzeichnis zeigen lassen oder alternativ das `run.sh` Skript verwenden um die Anwendung
  mit dem PHP internen Webserver auszuliefern.
* Das `data/` Verzeichnis sollte vom Benutzer, welcher PHP ausführt, beschreibbar sein.

## Konfiguration

Die Konfiguration kann in `app/configuration.php` vorgenommen werden.
Bisher lässt sich nur die optionale Schach-KI konfigurieren.

## Schach KI
* Kann durch `install-chenard.sh` installiert werden, build-tools werden benötigt!
* Das `run.sh` Skript startet die KI automatisch, mit entsprechender Konfiguration aus `app/configuration.php` 
