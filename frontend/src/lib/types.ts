export interface Course {
  id: number
  title: string
  description: string | null
  created_at: string
}

export interface LectureSummary {
  id: number
  course_id: number
  title: string
  status: string
  created_at: string
}

export interface Lecture extends LectureSummary {
  content: string
}


export interface Explanation {
  id: number
  concept_id: number
  style: string
  explanation: string | null
  example: string | null
  status: string
  attempts: number
}

export interface Concept {
  id: number
  position: number
  title: string
  summary: string
  source_quote: string
  explanations: Explanation[]
}

export interface LectureDetail extends Lecture {
  concepts: Concept[]
}