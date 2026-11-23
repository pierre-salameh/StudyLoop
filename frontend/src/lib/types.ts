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