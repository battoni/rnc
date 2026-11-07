import type { IDEA_AREA, IDEA_STATUS, IDEA_TYPE, NEWS_READ_FILTER } from './enums';

type IdeaStatusValue = `${IDEA_STATUS}`;

type IdeaTypeValue = `${IDEA_TYPE}`;

type IdeaAreaValue = `${IDEA_AREA}`;

type NewsReadFilter = `${NEWS_READ_FILTER}`;

export type { IdeaAreaValue, IdeaStatusValue, IdeaTypeValue, NewsReadFilter };
