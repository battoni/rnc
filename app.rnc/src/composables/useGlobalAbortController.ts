let controller = new AbortController();

function cancelAllRequests() {
  controller.abort();
}

function createNewController() {
  controller = new AbortController();
}

function getSignal(): AbortSignal {
  return controller.signal;
}

export function useGlobalAbortController() {
  return { cancelAllRequests, createNewController, getSignal };
}
