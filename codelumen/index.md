---
# https://vitepress.dev/reference/default-theme-home-page
layout: home

hero:
  name: "Code Lumen"
  tagline: battoni.dev internal handbook — projects, workflow, and conventions
  actions:
    - theme: brand
      text: Getting Started
      link: /about
    - theme: alt
      text: The Monorepo
      link: /monorepo

features:
  - icon: 🎯
    title: Code Conventions
    details: Establish clear coding standards that enable teams to work as one cohesive unit, ensuring consistency and maintainability across all projects.

  - icon: 🏗️
    title: Domain-Driven Design
    details: Implement scalable architecture using DDD principles, where everything related to a feature remains organized within the same domain folder structure.

  - icon: 🧩
    title: Component-Driven Development
    details: Build UI systems following Atomic Design principles, from atoms to molecules and organisms, creating reusable and maintainable components.

  - icon: 
      src: /tech/vue.png
      alt: Vue.js Logo
    title: Vue.js 3.5+ Expertise
    details: Leverage the latest Composition API with TypeScript for type-safe applications that scale with your business needs and deliver exceptional performance.

  - icon: 🛠️
    title: Modern Build Tools
    details: Use cutting-edge tools like Vite for lightning-fast development, Vitest for testing, and ESLint/Prettier for code quality enforcement.

  - icon: 🔗
    title: Atomic Architecture
    details: Follow strict component hierarchy rules where atoms import assets, molecules import atoms, and organisms import both, preventing circular dependencies.

  - icon: 📦
    title: Barrel File Pattern
    details: Implement clean dependency injection using barrel files that mimic Vue's Event Bus pattern, making imports consistent and maintainable.

  - icon: 🚀
    title: Performance First
    details: Build applications that prioritize speed and efficiency, with optimized code structures and modern development practices for exceptional user experiences.

  - icon: 🤝
    title: Team Collaboration
    details: Enable seamless teamwork through established conventions, clear documentation, and shared development patterns that make onboarding effortless.
---

