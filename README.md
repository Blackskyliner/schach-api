/play -> /game/z43qgdfbe35






Einfacher:

GET /games/ - Alle Spiele
POST /games/ - Spiel erstellen
    -> POST: opponent_type=ki|human&nickname=XXX
    <- POST: Authorization Token, Location: /game/{unique_id}
    
    if(opponent_type === human):
        if(Suche offenes Spiel mit fehlendem Opponent)
            Weise Spieler zu und erstelle Auth Token um als Gegner (schwarz|weiß) zu spielen.
        else
            Erstelle neues Game
            Bestimme Farbe (weiß|schwarz) die der Spieler haben soll
            Weise Spieler zu und erstelle Auth Token um als (schwarz|weiß) zu spielen.
    else
        Setze KI auf true
        Weise Spieler zu und erstelle Auth Token um als (schwarz|weiß) zu spielen.
        
GET /games/{unique_id} - Details über das Spiel 
    weiß: {Name A}
    schwarz: {Name B}|KI
    history: {
        // Historie von Bewegungen (PGN oder Algebraic Notation)
    }

PATCH /games/{unique_id} - Einen Zug hinzufügen
    
    
    
    // NEU ÜBERDENKEN?!