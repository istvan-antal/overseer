import { WidgetsAction, Widget } from '../actions/widgets';

type State = Widget[];

const defaultState: State = [];

export const widgets = (state = defaultState, action: WidgetsAction) => {
    switch (action.type) {
    case 'widgets/receive':
        return action.data;
    default:
        return state;
    }
};