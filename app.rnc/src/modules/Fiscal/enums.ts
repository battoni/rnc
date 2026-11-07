/** A client's readiness (api `ReadinessStatus`, R18): 95/80, any invoice at risk is crítico; no sales listed last. */
enum READINESS_STATUS {
  ATENCAO = 'atencao',
  CRITICO = 'critico',
  OK = 'ok',
  SEM_NOTAS = 'sem_notas',
}

/** What an incomplete invoice misses, worst first (api `ReadinessIssue`, R19): only the group is the 1115 risk. */
enum READINESS_ISSUE {
  MISSING_CODES = 'missing_codes',
  MISSING_GROUP = 'missing_group',
  WITHOUT_TEST_VALUES = 'without_test_values',
}

/** Why a file of an import was not stored (api `NfeRefusal`, R17). */
enum NFE_REFUSAL {
  CLIENT_UNKNOWN = 'client_unknown',
  DUPLICATE = 'duplicate',
  INVALID_KEY = 'invalid_key',
  NOT_NFE = 'not_nfe',
  NOT_XML = 'not_xml',
  TOO_LARGE = 'too_large',
}

/** How a batch came in (api `NfeImportSource`): the screen's upload or the `nfe:import` folder command. */
enum NFE_IMPORT_SOURCE {
  FOLDER = 'folder',
  UPLOAD = 'upload',
}

/** The lines the fiscal screens write on a client's timeline (api Portfolio `ClientEventType`, R20, R22). */
enum CLIENT_EVENT_TYPE {
  NFE_NOTICE = 'nfe.aviso',
  SUPPLIER_REVIEW = 'fornecedores.revisao',
}

export { CLIENT_EVENT_TYPE, NFE_IMPORT_SOURCE, NFE_REFUSAL, READINESS_ISSUE, READINESS_STATUS };
