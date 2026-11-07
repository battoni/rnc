---
outline: deep
---

# Icons

This project is using both [FontAwesome](https://fontawesome.com/) library and PrimeVue's icons. The FontAwesome library was installed in the project as a plugin, so that it can be exported globally and called in the template without the need to import it in the script. The PrimeVue icons are using directly when using their components.

## Adding new Icons

### FontAwesome

1. Access `Plugins/FontAwesome/icons.ts`
2. Import the Icon by name.
   > eg: `faTrash`
3. List the Icon on the `interface FaIcons`.
   > eg: `faTrash: IconDefinition;`
4. List the icon in the const ICONS so it can be exported.
   > eg: `faTrash,`

## Adding to a component

### PrimeVue

```Vue
<i class="pi pi-cog"></i>
```

### FontAwesome

```Vue
<FaIcon
  class="your-class"
  :icon="$icons.trash"
/>
```
