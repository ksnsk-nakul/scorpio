<x-mail::message>
# New Contact Message

You received a new message from your portfolio contact form.

**From:** {{ $submission->name }}
**Email:** {{ $submission->email }}

**Message:**

{{ $submission->message }}

---

*Reply directly to this email to respond to {{ $submission->name }}.*
</x-mail::message>
