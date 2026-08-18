export default async function handler(request) {
  const json = (data, status = 200) => {
    return new Response(JSON.stringify(data), {
      status,
      headers: {
        "Content-Type": "application/json; charset=utf-8",
        "Cache-Control": "no-store"
      }
    });
  };

  if (request.method !== "POST") {
    return json(
      {
        success: false,
        message: "Method not allowed"
      },
      405
    );
  }

  try {
    const data = await request.json();

    const {
      first_name,
      last_name,
      from_email,
      phone,
      subject,
      message
    } = data;

    if (
      !first_name ||
      !last_name ||
      !from_email ||
      !phone ||
      !subject ||
      !message
    ) {
      return json(
        {
          success: false,
          message: "All fields are required"
        },
        400
      );
    }

    const publicKey = process.env.EMAILJS_PUBLIC_KEY;
    const privateKey = process.env.EMAILJS_PRIVATE_KEY;
    const serviceId = process.env.EMAILJS_SERVICE_ID;
    const templateId = process.env.EMAILJS_TEMPLATE_ID;

    if (!publicKey || !privateKey || !serviceId || !templateId) {
      console.error("Missing EmailJS environment variables");

      return json(
        {
          success: false,
          message: "Email service is not configured"
        },
        500
      );
    }

    const emailResponse = await fetch(
      "https://api.emailjs.com/api/v1.0/email/send",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          service_id: serviceId,
          template_id: templateId,
          user_id: publicKey,
          accessToken: privateKey,
          template_params: {
            first_name,
            last_name,
            from_email,
            phone,
            subject,
            message
          }
        })
      }
    );

    const emailResponseText = await emailResponse.text();

    console.log("EmailJS status:", emailResponse.status);
    console.log("EmailJS response:", emailResponseText);

    if (!emailResponse.ok) {
      return json(
        {
          success: false,
          message: "EmailJS failed to send the email"
        },
        500
      );
    }

    // IMPORTANT:
    // Always return JSON to the frontend.
    return json({
      success: true,
      message: "Email sent successfully"
    });

  } catch (error) {
    console.error("Server error:", error);

    return json(
      {
        success: false,
        message: "Server error"
      },
      500
    );
  }
}