export default async function handler(request) {
  // --------------------------------------------------
  // JSON RESPONSE HELPER
  // --------------------------------------------------
  const json = (data, status = 200) => {
    return new Response(JSON.stringify(data), {
      status,
      headers: {
        "Content-Type": "application/json; charset=utf-8",
        "Cache-Control": "no-store",
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Methods": "POST, OPTIONS, GET",
        "Access-Control-Allow-Headers": "Content-Type, Accept"
      }
    });
  };

  // --------------------------------------------------
  // CORS PREFLIGHT
  // --------------------------------------------------
  if (request.method === "OPTIONS") {
    return json({
      success: true,
      message: "CORS OK"
    });
  }

  // --------------------------------------------------
  // GET = API TEST
  // --------------------------------------------------
  if (request.method === "GET") {
    return json({
      success: true,
      message: "Email API is working"
    });
  }

  // --------------------------------------------------
  // ONLY POST IS ALLOWED FOR SENDING EMAIL
  // --------------------------------------------------
  if (request.method !== "POST") {
    return json(
      {
        success: false,
        message: "Method not allowed. Use POST."
      },
      405
    );
  }

  // --------------------------------------------------
  // MAIN EMAIL FUNCTION
  // --------------------------------------------------
  try {
    // ----------------------------------------------
    // READ REQUEST BODY
    // ----------------------------------------------
    let data;

    try {
      data = await request.json();
    } catch (error) {
      console.error("Invalid JSON request:", error);

      return json(
        {
          success: false,
          message: "Invalid JSON request body"
        },
        400
      );
    }

    // ----------------------------------------------
    // GET FORM DATA
    // ----------------------------------------------
    const {
      first_name,
      last_name,
      from_email,
      phone,
      subject,
      message
    } = data || {};

    // ----------------------------------------------
    // VALIDATE REQUIRED FIELDS
    // ----------------------------------------------
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

    // ----------------------------------------------
    // EMAIL VALIDATION
    // ----------------------------------------------
    const emailPattern =
      /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailPattern.test(String(from_email).trim())) {
      return json(
        {
          success: false,
          message: "Please enter a valid email address"
        },
        400
      );
    }

    // ----------------------------------------------
    // READ VERCEL ENVIRONMENT VARIABLES
    // ----------------------------------------------
    const publicKey =
      process.env.EMAILJS_PUBLIC_KEY;

    const privateKey =
      process.env.EMAILJS_PRIVATE_KEY;

    const serviceId =
      process.env.EMAILJS_SERVICE_ID;

    const templateId =
      process.env.EMAILJS_TEMPLATE_ID;

    // ----------------------------------------------
    // CHECK ENVIRONMENT VARIABLES
    // ----------------------------------------------
    if (
      !publicKey ||
      !privateKey ||
      !serviceId ||
      !templateId
    ) {
      console.error(
        "Missing EmailJS environment variables"
      );

      return json(
        {
          success: false,
          message:
            "Email service is not configured. Check Vercel Environment Variables."
        },
        500
      );
    }

    // ----------------------------------------------
    // SEND EMAIL THROUGH EMAILJS REST API
    // ----------------------------------------------
    const emailResponse = await fetch(
      "https://api.emailjs.com/api/v1.0/email/send",
      {
        method: "POST",

        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },

        body: JSON.stringify({
          service_id: serviceId,

          template_id: templateId,

          user_id: publicKey,

          accessToken: privateKey,

          template_params: {
            first_name: String(first_name).trim(),

            last_name: String(last_name).trim(),

            from_email: String(from_email).trim(),

            phone: String(phone).trim(),

            subject: String(subject).trim(),

            message: String(message).trim()
          }
        })
      }
    );

    // ----------------------------------------------
    // READ EMAILJS RESPONSE
    // ----------------------------------------------
    const emailResponseText =
      await emailResponse.text();

    console.log(
      "EmailJS HTTP Status:",
      emailResponse.status
    );

    console.log(
      "EmailJS Response:",
      emailResponseText
    );

    // ----------------------------------------------
    // EMAILJS ERROR
    // ----------------------------------------------
    if (!emailResponse.ok) {
      let emailError =
        emailResponseText ||
        "EmailJS failed to send the email.";

      // Don't expose private credentials
      emailError = emailError
        .replace(privateKey, "[hidden]");

      return json(
        {
          success: false,
          message:
            "EmailJS failed: " + emailError
        },
        500
      );
    }

    // ----------------------------------------------
    // SUCCESS
    // ----------------------------------------------
    return json({
      success: true,
      message: "Email sent successfully"
    });
  } catch (error) {
    // ----------------------------------------------
    // SERVER ERROR
    // ----------------------------------------------
    console.error(
      "Email server error:",
      error
    );

    return json(
      {
        success: false,
        message:
          error?.message ||
          "Internal server error"
      },
      500
    );
  }
}
