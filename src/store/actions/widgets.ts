import { createAction, ActionsUnion } from '.';

export interface Widget {
    nickname: string;
    id: string;
    baseUrl: string;
    _links: {
        self: {
            href: string;
        };
    };
    result: string;
    nodes: Array<{
        id: string;
        type: string;
        result: string;
        displayName: string;
        actions: Array<{
            link: {
                href: string;
            };
            _class: string;
            description: string;
        }>;
    }>;
}

export const receiveWidgetsActions = {
    receive: (widgets: Widget[]) => createAction('widgets/receive', widgets),
};

export type WidgetsAction = ActionsUnion<typeof receiveWidgetsActions>;