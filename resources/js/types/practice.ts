export type Drill = {
    id: number;
    name: string;
    category: string;
    duration: number;
    last_run: string | null;
};

export type Segment = {
    key: string;
    title: string;
    duration: number;
    notes: string | null;
    drill_id: number | null;
    play_id: number | null;
};

export type PracticeRecord = {
    id: number;
    name: string;
    date: string | null;
    start_time: string | null;
    duration: number;
    location: string | null;
    objectives: string | null;
    notes: string | null;
    review_notes: string | null;
    is_template: boolean;
    segments: Omit<Segment, 'key'>[];
};

export type RosterPlayer = {
    id: number;
    name: string;
    number: string | null;
};

/** What a library item carries while it is dragged onto the timeline. */
export type DragPayload = { type: 'drill' | 'play'; id: number };
