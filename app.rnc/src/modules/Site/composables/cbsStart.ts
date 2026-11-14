import { CBS_START } from '../constants';

/*
 * Copy that speaks of the CBS's date as still ahead ("começa a ser cobrada em 01/01/2027", "antes de 2027") turns false
 * the day it passes (rnc-4 verify P2-S2). Such a key has a post-date twin, `<key>Started`, chosen on the countdown's
 * own condition (`MCbsCountdown` shows `site.countdown.started` from the same instant), so the page never says two
 * things at once.
 */
function hasCbsStarted(): boolean {
  return Date.now() >= CBS_START.getTime();
}

/** The key, or its `<key>Started` twin once the CBS is charged. */
function cbsKey(key: string): string {
  return hasCbsStarted() ? `${key}Started` : key;
}

export { cbsKey, hasCbsStarted };
