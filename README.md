# Form Builder

PHP API + Vue 3 + TypeScript + Tailwind CSS Anwendung für dynamische Formularerstellung mit Ordnerstruktur, Drag & Drop Feld-Platzierung und öffentlichen Freigabe-Links.

## Features

- **Admin-Bereich**: Ordnerstruktur anlegen, verschieben, kopieren
- **Canvas/Bild**: Felder per Drag & Drop auf Hintergrundbild platzieren
- **Feldtypen**: Text, Textarea, WYSIWYG, Upload, Multi-Upload, Auswahl, Wiederholbare Referenzen
- **Öffentliche URLs**: Teilen von Ordner-Bäumen per Link
- **Validierung**: Pflichtfelder, Mindestzeichen, Mindesteinträge
- **Fortschrittsanzeige**: Prozent und Farben in Sidebar und Ordnerbaum
- **Zwischenspeichern**: Jederzeit speichern, Freigabe erst wenn alles ausgefüllt
- **E-Mail-Benachrichtigung**: Bei Freigabe wird eine E-Mail versendet
- **ZIP-Download**: Ordner inkl. Unterordner als ZIP mit .txt Datendatei

## Setup

### Mit Docker Compose

```bash
docker-compose up
```

- Frontend: http://localhost:3000
- Backend API: http://localhost:8080

### Manuell

**Backend:**
```bash
cd backend
php -S localhost:8080 router.php
```

**Frontend:**
```bash
cd frontend
npm install
npm run dev
```

## Login

- **Benutzer**: admin
- **Passwort**: password

## Architektur

```
backend/
  api/
    index.php       # Router + alle API Endpoints
    helpers.php     # Hilfsfunktionen (JWT, Folder-Ops, ZIP)
  config/
    config.php      # Konfiguration
  storage/
    folders/        # JSON Dateien pro Ordner
    uploads/        # Hochgeladene Dateien
  router.php        # PHP Built-in Server Router

frontend/
  src/
    components/     # Vue Komponenten
    composables/    # API Client
    types/          # TypeScript Typen
    views/          # Seiten (Admin, Login, Public)
```

## API Endpoints

| Method | Endpoint | Beschreibung |
|--------|---------|-------------|
| POST | /api/auth/login | Admin Login |
| GET | /api/folders/tree | Ordnerbaum abrufen |
| POST | /api/folders | Ordner erstellen |
| PUT | /api/folders/move | Ordner verschieben |
| PUT | /api/folders/:id | Ordner umbenennen |
| POST | /api/folders/:id/copy | Ordner kopieren (mit Struktur) |
| DELETE | /api/folders/:id | Ordner löschen |
| POST | /api/folders/:id/share | Freigabe-Link erstellen |
| GET | /api/folders/:id/data | Ordnerdaten abrufen |
| PUT | /api/folders/:id/fields | Felder aktualisieren |
| PUT | /api/folders/:id/canvas | Canvas-Bild setzen |
| POST | /api/upload | Datei hochladen |
| GET | /api/download/:id | ZIP Download |
| GET | /api/public/:token | Öffentlicher Ordnerbaum |
| GET | /api/public/:token/folder/:id | Öffentliche Ordnerdaten |
| PUT | /api/public/:token/folder/:id | Daten speichern |
| POST | /api/public/:token/folder/:id/release | Formular freigeben |
