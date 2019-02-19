export interface Action<T extends string> {
    type: T;
}

export interface ActionWithData<T extends string, P> extends Action<T> {
    data: P;
}

// tslint:disable:only-arrow-functions
export function createAction<T extends string>(type: T): Action<T>;
export function createAction<T extends string, P>(type: T, data: P): ActionWithData<T, P>;
export function createAction<T extends string, P>(type: T, data?: P) {
    return data === undefined ? { type } : ({ type, data });
}

// tslint:disable-next-line:no-any
type FunctionType = (...args: any[]) => any;
interface ActionCreatorMapObject { [actionCreator: string]: FunctionType; }
export type ActionsUnion<A extends ActionCreatorMapObject> = ReturnType<A[keyof A]>;

// Example actions/counter.ts
/*
import { createAction, ActionsUnion } from '.';

export const counterActions = {
    reset: () => createAction('reset'),
    increment: (amount = 1) => createAction('increment', amount),
    decrement: (amount = 1) => createAction('decrement', amount),
}

export type CounterActions = ActionsUnion<typeof counterActions>;
*/

// Example reducers/counter.ts
/*
import { CounterActions } from '../actions/counter';

export const counter = (state = 0, action: CounterActions) => {
    switch (action.type) {
    case 'reset':
        return 0;
    case 'increment':
        return state + action.data;
    case 'decrement':
        return state - action.data;
    default:
        return state;
    }
}
*/
