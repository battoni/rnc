import { faXmark } from '@fortawesome/free-solid-svg-icons';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';

interface FaIcons {
  [key: string]: string | IconDefinition;
  faXmark: IconDefinition;
}

const ICONS: FaIcons = {
  faXmark,
};

export type { FaIcons };
export default ICONS;
