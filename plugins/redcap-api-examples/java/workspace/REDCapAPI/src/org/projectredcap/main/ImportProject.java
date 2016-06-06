package org.projectredcap.main;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.util.ArrayList;
import java.util.List;

import org.apache.http.HttpResponse;
import org.apache.http.NameValuePair;
import org.apache.http.client.HttpClient;
import org.apache.http.client.entity.UrlEncodedFormEntity;
import org.apache.http.client.methods.HttpPost;
import org.apache.http.impl.client.HttpClientBuilder;
import org.apache.http.message.BasicNameValuePair;
import org.json.simple.JSONArray;
import org.json.simple.JSONObject;

public class ImportProject
{
	private final List<NameValuePair> params;
	private final HttpPost post;
	private HttpResponse resp;
	private final HttpClient client;
	private int respCode;
	private BufferedReader reader;
	private final StringBuffer result;
	private String line;
	private final JSONObject record;
	private final JSONArray data;

	@SuppressWarnings("unchecked")
	public ImportProject(final Config c)
	{
		record = new JSONObject();
		record.put("project_title", "Project ABC");
		record.put("purpose", "0");
		record.put("purpose_other", "");
		record.put("project_notes", "Some notes about the project");

		data = new JSONArray();
		data.add(record);

		params = new ArrayList<NameValuePair>();
		params.add(new BasicNameValuePair("token", c.API_SUPER_TOKEN));
		params.add(new BasicNameValuePair("content", "project"));
		params.add(new BasicNameValuePair("format", "json"));
		params.add(new BasicNameValuePair("data", data.toJSONString()));

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

		result = new StringBuffer();
		client = HttpClientBuilder.create().build();
		respCode = -1;
		reader = null;
		line = null;
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

				try
				{
					reader = new BufferedReader(new InputStreamReader(resp.getEntity().getContent()));
				}
				catch (final Exception e)
				{
					e.printStackTrace();
				}
			}

		if(reader != null)
		{
			try
			{
				while((line = reader.readLine()) != null)
				{
					result.append(line);
				}
			}
			catch (final Exception e)
			{
				e.printStackTrace();
			}
		}

		System.out.println("respCode: " + respCode);
		System.out.println("result: " + result.toString());
	}
}
