# Font Download Instructions

This directory should contain the following font files:

## Directory Structure

```
public/fonts/
├── space-grotesk/
│   ├── SpaceGrotesk-Light.woff2
│   ├── SpaceGrotesk-Regular.woff2
│   ├── SpaceGrotesk-Medium.woff2
│   ├── SpaceGrotesk-SemiBold.woff2
│   └── SpaceGrotesk-Bold.woff2
└── sometype-mono/
    ├── SometypeMono-Regular.woff2
    ├── SometypeMono-Medium.woff2
    └── SometypeMono-Bold.woff2
```

## Download Instructions

### Option 1: Using google-webfonts-helper (Recommended)

1. **Space Grotesk:**
   - Visit: https://gwfh.mranftl.com/fonts/space-grotesk
   - Select charsets: latin, latin-ext
   - Select styles: 300, 400, 500, 600, 700
   - Select formats: woff2 only
   - Click "Download files"
   - Extract and rename files to match the structure above
   - Place in `public/fonts/space-grotesk/`

2. **Sometype Mono:**
   - Visit: https://gwfh.mranftl.com/fonts/sometype-mono
   - Select charsets: latin, latin-ext
   - Select styles: 400, 500, 700
   - Select formats: woff2 only
   - Click "Download files"
   - Extract and rename files to match the structure above
   - Place in `public/fonts/sometype-mono/`

### Option 2: Direct from Google Fonts

1. Visit https://fonts.google.com/
2. Search for "Space Grotesk" and "Sometype Mono"
3. Download the font families
4. Convert to woff2 format if needed
5. Organize according to the directory structure above

### Option 3: Using fontsource npm packages

```bash
# Install the fonts as npm packages
npm install @fontsource/space-grotesk @fontsource/sometype-mono

# Then copy the woff2 files from node_modules to public/fonts/
```

## Verification

After downloading, verify your directory structure:

```bash
ls -R public/fonts/
```

You should see both font directories with the woff2 files listed above.

## File Naming

Make sure the files are named exactly as shown in the structure above. The CSS references these specific filenames.

