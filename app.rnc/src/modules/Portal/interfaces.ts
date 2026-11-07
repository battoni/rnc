/** Who is signed in to the portal (`PortalMeResource`). */
interface PortalMe {
  firm: { name: string; slug: string };
  partner: { id: number; name: string; specialty: string };
}

export type { PortalMe };
