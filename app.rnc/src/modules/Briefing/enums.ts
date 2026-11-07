/** "As 3 ações de hoje" (rnc-3 R25): the API's `ActionPicker` kinds, in its order. */
enum BRIEFING_ACTION_KIND {
  SLA_BREACHED = 'sla_breached',
  MEETINGS_TODAY = 'meetings_today',
  PROPOSALS = 'proposals',
  PARTIAL_LEADS = 'partial_leads',
  RADAR = 'radar',
}

/** The Radar action's range is per year, or per month for the split-payment rule (api `RadarService`). */
enum BRIEFING_RADAR_PERIOD {
  MONTH = 'mes',
  YEAR = 'ano',
}

export { BRIEFING_ACTION_KIND, BRIEFING_RADAR_PERIOD };
