interface PaginatorLinks {
  active: boolean;
  label: string;
  url: string | null;
}

interface PaginateResult<T = any> {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: PaginatorLinks[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

type ApiQueryValue = string | number | boolean | undefined | null;

type ApiQuery = Record<string, ApiQueryValue | ApiQueryValue[]>;

interface ApiParams {
  endpoint: string;
  forwardError?: boolean;
  /** Merged over the defaults — how a service sends a header of its own (a token, a locale). */
  headers?: Record<string, string>;
  isUpload?: boolean;
  method?: 'get' | 'post' | 'delete' | 'patch' | 'put';
  payload?: any;
  query?: ApiQuery;
}

interface ApiResponse<T = any> {
  data: T;
}

export type { ApiParams, ApiQuery, ApiQueryValue, ApiResponse, PaginateResult };
