# Anforderungen
- PHP >=5.4

# Installation
Das öffentliche Verzeichnis der API ist das web/ Verzeichnis. 
In diesem befindet sich die index.php welche vom Webserver ausgeführt werden muss.

Eine .htaccess liegt bei und nutzt dabei, sofern vorhanden, 
mod_rewrite damit die index.php nicht zwangsweise in der URL mit angegeben werden muss.

Der Server sollte dementsprechend auf das web/ Verzeichnis zeigen.
Zu Testzwecken kann alternativ der in PHP integrierte Server verwendet werden, um das web/ Verzeichnis bereitzustellen.

Das Verzeichnis data/ sollte durch den Webserver beschreibbar sein, 
da dort die, von der API verwalteten, Daten abgelegt werden.

# Zusatz

Das Script bietet die Möglichkeit einer KI einen Zug bestimmen lassen zu können.
Dafür muss ein Chenerver auf einem System vorhanden sein.
    https://github.com/cosinekitty/chenard/wiki/ChenServer
Da dieser einen eigenen globalen Zustand hält, eignet dieser sich nur für das Testen.
Um für eine Partie die KI einen Zug machen zu lassen, kann der PATCH Endpunkt einer Partie verwendet werden.

Die Konfiguration der KI kann in app/configuration.php vorgenommen werden.
