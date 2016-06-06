package org.projectredcap.main;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.util.ArrayList;
import java.util.List;

import org.apache.http.HttpResponse;
import org.apache.http.NameValuePair;
import org.apache.http.client.HttpClient;
import org.apache.http.client.entity.UrlEncodedFormEntity;
import org.apache.http.client.methods.HttpPost;
import org.apache.http.impl.client.HttpClientBuilder;
import org.apache.http.message.BasicNameValuePair;

public class ExportFile
{
	private final List<NameValuePair> params;
	private final HttpPost post;
	private HttpResponse resp;
	private final HttpClient client;
	private int respCode;
	private InputStream is;
	private FileOutputStream fos;
	private int read;
	private final byte[] buf;

	public ExportFile(final Config c)
	{
		params = new ArrayList<NameValuePair>();
		params.add(new BasicNameValuePair("token", c.API_TOKEN));
		params.add(new BasicNameValuePair("content", "file"));
		params.add(new BasicNameValuePair("action", "export"));
		params.add(new BasicNameValuePair("record", "f21a3ffd37fc0b3c"));
		params.add(new BasicNameValuePair("field", "file_upload"));
		params.add(new BasicNameValuePair("event", "event_1_arm_1"));

		post = new HttpPost(c.API_URL);
		post.setHeader("Content-Type", "application/x-www-form-urlencoded");

		try
		{
			post.setEntity(new UrlEncodedFormEntity(params));
		}
		catch (final Exception e)
		{
			e.printStackTrace();
		}

		client = HttpClientBuilder.create().build();
		respCode = -1;
		is = null;
		fos = null;
		read = 0;
		buf = new byte[4096];
	}

	public void doPost()
	{
		resp = null;

		try
		{
			resp = client.execute(post);
		}
		catch (final Exception e)
		{
			e.printStackTrace();
		}

		if(resp != null)
		{
			respCode = resp.getStatusLine().getStatusCode();
			System.out.println("respCode: " + respCode);

			if(respCode != 200)
			{
				return;
			}

			try
			{
				is = resp.getEntity().getContent();
			}
			catch (final Exception e)
			{
				e.printStackTrace();
			}

			try
			{
				fos = new FileOutputStream(new File("/tmp/file.raw"));
			}
			catch (final Exception e)
			{
				e.printStackTrace();
			}

			try
			{
				while((read = is.read(buf)) > 0)
				{
					fos.write(buf, 0, read);
				}
			}
			catch (final Exception e)
			{
				e.printStackTrace();
			}

			try
			{
				fos.close();
			}
			catch (final Exception e)
			{
				e.printStackTrace();
			}

			try
			{
				is.close();
			}
			catch (final Exception e)
			{
				e.printStackTrace();
			}
		}
	}
}
